<?php
require_once './datos/ConexionBD.php';
require_once './utilidades/ClienteFtp.php';
require_once 'Alumno.php';

// --- INICIO: Iniciar sesión ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// --- FIN: Iniciar sesión ---

class Ftp
{
    private $conn;
    private $server;
    private $username;
    private $password;
    private $port;

    // Constantes de estado
    const ESTADO_URL_INCORRECTA = 1;
    const ESTADO_CREACION_EXITOSA = 2;
    const ESTADO_CREACION_FALLIDA = 3;
    const ESTADO_FALLA_DESCONOCIDO = 4;
    const ESTADO_ERROR_BD = 5;
    const ESTADO_PARAMETROS_INCORRECTOS = 7;
    const ESTADO_CLAVE_NO_AUTORIZADA = 8;
    const ESTADO_AUSENCIA_CLAVE_API = 9;

    public function __construct($server, $username, $password, $port = 21)
    {
        $this->server = $server;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }

    public function getConn()
    {
        return $this->conn;
    }

    public function desconectar()
    {
        if ($this->conn) {
            ftp_close($this->conn);
            $this->conn = null;
        }
    }

    public function conectar()
    {
        try {
            $this->conn = ftp_connect($this->server, $this->port);
            if (!$this->conn) {
                throw new Exception("No se pudo conectar al servidor FTP");
            }

            if (!ftp_login($this->conn, $this->username, $this->password)) {
                throw new Exception("Error al iniciar sesión en el servidor FTP");
            }

            // Habilitar modo pasivo
            if (!ftp_pasv($this->conn, true)) {
                throw new Exception("Error al configurar el modo pasivo FTP");
            }

            return true;
        } catch (Exception $e) {
            throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, $e->getMessage());
        }
    }

    private function obtenerListaArchivos()
    {
        $archivos = [];
        $listaArchivos = ftp_nlist($this->conn, ".");

        if ($listaArchivos === false) {
            throw new Exception("Error al listar archivos del servidor FTP");
        }

        foreach ($listaArchivos as $archivo) {
            $tamaño = ftp_size($this->conn, $archivo);
            $fechaModificacion = ftp_mdtm($this->conn, $archivo);

            $archivos[] = [
                "nombre" => $archivo,
                "tamaño" => $tamaño !== -1 ? $tamaño : 0,
                "fechaModificacion" => $fechaModificacion !== -1 ? date('Y-m-d H:i:s', $fechaModificacion) : date('Y-m-d H:i:s')
            ];
        }

        return $archivos;
    }

    public static function get($parameters)
    {
        try {
            $idAlumno = Alumno::autorizar();

            if (!isset($idAlumno)) {
                throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
            }

            // Obtener la configuración FTP de la sesión
            $ftpConfig = $_SESSION['ftp_config'] ?? null;
            if (!$ftpConfig) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No hay configuración FTP activa");
            }

            $ftp = new self($ftpConfig['server'], $ftpConfig['username'], $ftpConfig['password'], $ftpConfig['port']);

            if (!$ftp->conectar()) {
                throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, "Error al conectar con el servidor FTP");
            }

            // Si se proporciona un nombre de archivo específico, descargar ese archivo
            if (!empty($parameters)) {
                $nombreArchivo = $parameters[0];
                $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');

                if (!ftp_get($ftp->getConn(), $tempFile, $nombreArchivo, FTP_BINARY)) {
                    unlink($tempFile);
                    throw new Exception("Error al descargar el archivo");
                }

                // Leer el contenido del archivo
                $contenido = file_get_contents($tempFile);
                unlink($tempFile);

                // Configurar headers CORS y de descarga
                header("Access-Control-Allow-Origin: http://localhost:5173");
                header("Access-Control-Allow-Credentials: true");
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
                header("Access-Control-Allow-Headers: Content-Type, Authorization");
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
                header('Content-Length: ' . strlen($contenido));
                echo $contenido;
                exit;
            }

            // Listar todos los archivos
            $archivos = $ftp->obtenerListaArchivos();
            $ftp->desconectar();

            return [
                "estado" => self::ESTADO_CREACION_EXITOSA,
                "archivos" => $archivos
            ];
        } catch (ExcepcionApi $e) {
            if (isset($ftp)) {
                $ftp->desconectar();
            }
            throw $e;
        } catch (Exception $e) {
            if (isset($ftp)) {
                $ftp->desconectar();
            }
            throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, $e->getMessage());
        }
    }

    public static function post($parameters)
    {
        try {
            $idAlumno = Alumno::autorizar();

            if (!isset($idAlumno)) {
                throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
            }

            $body = file_get_contents('php://input');
            $datos = json_decode($body);

            // Si es una conexión FTP
            if (isset($datos->server) && isset($datos->username) && isset($datos->password)) {
                $port = isset($datos->port) ? $datos->port : 21;
                $ftp = new self($datos->server, $datos->username, $datos->password, $port);

                if ($ftp->conectar()) {
                    // Guardar la configuración en la sesión
                    $_SESSION['ftp_config'] = [
                        'server' => $datos->server,
                        'username' => $datos->username,
                        'password' => $datos->password,
                        'port' => $port
                    ];

                    // Listar archivos después de conectar
                    $archivos = $ftp->obtenerListaArchivos();
                    $ftp->desconectar();

                    return [
                        "estado" => self::ESTADO_CREACION_EXITOSA,
                        "mensaje" => "Conexión exitosa",
                        "archivos" => $archivos
                    ];
                }
            }
            // Si es una subida de archivo
            else if (isset($datos->archivo) && isset($datos->contenido)) {
                $ftpConfig = $_SESSION['ftp_config'] ?? null;
                if (!$ftpConfig) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No hay configuración FTP activa");
                }

                $ftp = new self($ftpConfig['server'], $ftpConfig['username'], $ftpConfig['password'], $ftpConfig['port']);

                if (!$ftp->conectar()) {
                    throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, "Error al conectar con el servidor FTP");
                }

                // Decodificar el contenido base64
                $contenido = base64_decode($datos->contenido);
                if ($contenido === false) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Error al decodificar el contenido del archivo");
                }

                // Crear archivo temporal
                $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
                if (file_put_contents($tempFile, $contenido) === false) {
                    unlink($tempFile);
                    throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al crear archivo temporal");
                }

                try {
                    // Subir archivo
                    if (!ftp_put($ftp->getConn(), $datos->archivo, $tempFile, FTP_BINARY)) {
                        throw new Exception("Error al subir el archivo al servidor FTP");
                    }

                    // Obtener lista actualizada de archivos
                    $archivos = $ftp->obtenerListaArchivos();

                    unlink($tempFile);
                    $ftp->desconectar();

                    return [
                        "estado" => self::ESTADO_CREACION_EXITOSA,
                        "mensaje" => "Archivo subido correctamente",
                        "archivos" => $archivos
                    ];
                } catch (Exception $e) {
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                    throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, $e->getMessage());
                }
            }

            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Datos incorrectos");
        } catch (ExcepcionApi $e) {
            if (isset($ftp)) {
                $ftp->desconectar();
            }
            throw $e;
        } catch (Exception $e) {
            if (isset($ftp)) {
                $ftp->desconectar();
            }
            throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, $e->getMessage());
        }
    }

    public static function delete($parameters)
    {
        try {
            $idAlumno = Alumno::autorizar();

            if (!isset($idAlumno)) {
                throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
            }

            $ftpConfig = $_SESSION['ftp_config'] ?? null;
            if (!$ftpConfig) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No hay configuración FTP activa");
            }

            $ftp = new self($ftpConfig['server'], $ftpConfig['username'], $ftpConfig['password'], $ftpConfig['port']);

            if (!$ftp->conectar()) {
                throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, "Error al conectar con el servidor FTP");
            }

            // Si se proporciona un nombre de archivo, eliminarlo
            if (!empty($parameters)) {
                $nombreArchivo = $parameters[0];
                if (!ftp_delete($ftp->getConn(), $nombreArchivo)) {
                    throw new Exception("Error al eliminar el archivo");
                }

                // Obtener lista actualizada de archivos
                $archivos = $ftp->obtenerListaArchivos();
                $ftp->desconectar();

                return [
                    "estado" => self::ESTADO_CREACION_EXITOSA,
                    "mensaje" => "Archivo eliminado correctamente",
                    "archivos" => $archivos
                ];
            }

            // Si no se proporciona nombre de archivo, desconectar la sesión FTP
            $ftp->desconectar();
            unset($_SESSION['ftp_config']); // Solo eliminamos la configuración al desconectar explícitamente

            return [
                "estado" => self::ESTADO_CREACION_EXITOSA,
                "mensaje" => "Desconexión exitosa"
            ];
        } catch (ExcepcionApi $e) {
            if (isset($ftp)) {
                $ftp->desconectar();
            }
            throw $e;
        } catch (Exception $e) {
            if (isset($ftp)) {
                $ftp->desconectar();
            }
            throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, $e->getMessage());
        }
    }
}
?>