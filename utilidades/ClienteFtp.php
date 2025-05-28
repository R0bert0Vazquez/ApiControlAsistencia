<?php
require_once './controladores/Ftp.php';

// --- INICIO: Iniciar sesión ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// --- FIN: Iniciar sesión ---

class ClienteFtp {
    private static $instance = null;
    private $config = [
        'server' => 'tu_servidor_ftp',
        'username' => 'tu_usuario',
        'password' => 'tu_password',
        'port' => 21,
        'timeout' => 90
    ];

    private function __construct() {
        // Constructor privado para singleton
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setConfig($config) {
        $this->config = array_merge($this->config, $config);
    }

    public function getConfig() {
        return $this->config;
    }

    public function validarArchivo($archivo) {
        // Validar extensión
        $extensionesPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $extensionesPermitidas)) {
            throw new Exception("Tipo de archivo no permitido");
        }

        // Validar tamaño (máximo 10MB)
        if (filesize($archivo) > 10 * 1024 * 1024) {
            throw new Exception("El archivo excede el tamaño máximo permitido (10MB)");
        }

        return true;
    }

    public function generarNombreUnico($nombreOriginal) {
        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }

    public function crearDirectorio($ruta) {
        $ftp = new Ftp(
            $this->config['server'],
            $this->config['username'],
            $this->config['password'],
            $this->config['port']
        );

        try {
            $ftp->conectar();
            if (!ftp_nlist($ftp->getConn(), $ruta)) {
                ftp_mkdir($ftp->getConn(), $ruta);
            }
            $ftp->desconectar();
            return true;
        } catch (Exception $e) {
            throw new Exception("Error al crear directorio: " . $e->getMessage());
        }
    }

    public function moverArchivo($origen, $destino) {
        $ftp = new Ftp(
            $this->config['server'],
            $this->config['username'],
            $this->config['password'],
            $this->config['port']
        );

        try {
            $ftp->conectar();
            if (ftp_rename($ftp->getConn(), $origen, $destino)) {
                $ftp->desconectar();
                return true;
            }
            throw new Exception("Error al mover el archivo");
        } catch (Exception $e) {
            throw new Exception("Error al mover archivo: " . $e->getMessage());
        }
    }

    public function eliminarArchivo($ruta) {
        $ftp = new Ftp(
            $this->config['server'],
            $this->config['username'],
            $this->config['password'],
            $this->config['port']
        );

        try {
            $ftp->conectar();
            if (ftp_delete($ftp->getConn(), $ruta)) {
                $ftp->desconectar();
                return true;
            }
            throw new Exception("Error al eliminar el archivo");
        } catch (Exception $e) {
            throw new Exception("Error al eliminar archivo: " . $e->getMessage());
        }
    }

    public function obtenerTamañoArchivo($ruta) {
        $ftp = new Ftp(
            $this->config['server'],
            $this->config['username'],
            $this->config['password'],
            $this->config['port']
        );

        try {
            $ftp->conectar();
            $tamaño = ftp_size($ftp->getConn(), $ruta);
            $ftp->desconectar();
            return $tamaño;
        } catch (Exception $e) {
            throw new Exception("Error al obtener tamaño del archivo: " . $e->getMessage());
        }
    }

    public function formatearTamaño($bytes) {
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($unidades) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $unidades[$pow];
    }
}
?>