<?php
require_once "./datos/ConexionBD.php";

class Alumno
{
    //Datos de la tabla "alumnos"
    const NOMBRE_TABLA = "Alumnos";
    const ID = "id";
    const NOMBRE_COMPLETO = "nombreCompleto";
    const NUMERO_CONTROL = "numeroControl";
    const CONTRASENA = "contrasena";
    const CLAVE_API = "claveApi";
    const CARRERA_ID = "carreraId";
    const SEMESTRE = "semestre";

    //Constantes de estado para respuestas y errores
    const ESTADO_URL_INCORRECTA = 1;
    const ESTADO_CREACION_EXITOSA = 2;
    const ESTADO_CREACION_FALLIDA = 3;
    const ESTADO_FALLA_DESCONOCIDO = 4;
    const ESTADO_ERROR_BD = 5;
    const ESTADO_PARAMETROS_INCORRECTOS = 7;
    const ESTADO_CLAVE_NO_AUTORIZADA = 8;
    const ESTADO_AUSENCIA_CLAVE_API = 9;
    const ESTADO_EXITO = 2;


    public static function post($parameters)
    {
        if ($parameters[0] == 'registro') {
            return self::registrarAlumno();
        } else if ($parameters[0] == 'login') {
            return self::loguearAlumno();
        } else {
            throw new ExcepcionApi(self::ESTADO_URL_INCORRECTA, "Url mal formada", 400);
        }
    }

    private static function registrarAlumno()
    {
        $cuerpo = file_get_contents('php://input');
        $alumno = json_decode($cuerpo);

        $resultado = self::crearAlumno($alumno);

        switch ($resultado) {
            case self::ESTADO_CREACION_EXITOSA:
                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_CREACION_EXITOSA,
                        "mensaje" => ("¡Registro con exito!")
                    ];
            case self::ESTADO_CREACION_FALLIDA:
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Ha ocurrido un error");
            default:
                throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, "Falla desconocida", 400);
        }
    }

    private static function crearAlumno($datosAlumno)
    {
        $nombreCompleto = $datosAlumno->nombreCompleto;
        $numeroControl = $datosAlumno->numeroControl;
        $contrasena = $datosAlumno->contrasena;
        $contrasenaEnciptada = self::encriptarContrasena($contrasena);
        $claveApi = self::generarClaveApi();
        $carreraId = $datosAlumno->carreraId;
        $semestre = $datosAlumno->semestre;

        try {
            $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

            //Sentencia INSERT
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                self::NOMBRE_COMPLETO . "," .
                self::NUMERO_CONTROL . "," .
                self::CONTRASENA . "," .
                self::CLAVE_API . "," .
                self::CARRERA_ID . "," .
                self::SEMESTRE . ")" .
                " VALUES(?,?,?,?,?,?)";

            $sentencia = $pdo->prepare($comando);

            $sentencia->bindParam(1, $nombreCompleto);
            $sentencia->bindParam(2, $numeroControl);
            $sentencia->bindParam(3, $contrasenaEnciptada);
            $sentencia->bindParam(4, $claveApi);
            $sentencia->bindParam(5, $carreraId);
            $sentencia->bindParam(6, $semestre);

            $resultado = $sentencia->execute();

            if ($resultado) {
                return self::ESTADO_CREACION_EXITOSA;
            } else {
                return self::ESTADO_CREACION_FALLIDA;
            }

        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    private static function encriptarContrasena($contrasenaPlana)
    {
        if ($contrasenaPlana)
            return password_hash($contrasenaPlana, PASSWORD_DEFAULT);
        else
            return null;
    }
    private static function generarClaveApi()
    {
        return md5(microtime() . rand());
    }

    private static function loguearAlumno()
    {
        $respuesta = array();
        $body = file_get_contents('php://input');
        $alumno = json_decode($body);

        $numeroControl = $alumno->numeroControl;
        $contrasena = $alumno->contrasena;

        if (self::autentificarAlumno($numeroControl, $contrasena)) {
            $alumnoBD = self::obtenerAlumnoPorNumeroControl($numeroControl);

            if ($alumnoBD != NULL) {
                http_response_code(200);
                $respuesta["nombreCompleto"] = $alumnoBD["nombreCompleto"];
                $respuesta["numeroControl"] = $alumnoBD["numeroControl"];
                $respuesta["claveApi"] = $alumnoBD["claveApi"];
                $respuesta["carreraId"] = $alumnoBD["carreraId"];
                $respuesta["semestre"] = $alumnoBD["semestre"];
                return ["estado" => 2, "alumno" => $respuesta];
            } else {
                throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, "Ha ocurrido un error");
            }
        } else {
            throw new ExcepcionApi(
                self::ESTADO_PARAMETROS_INCORRECTOS,
                ("Numero Control o contraseña invalidos")
            );
        }
    }

    private static function autentificarAlumno($numeroControl, $contrasena)
    {
        $comando = "SELECT contrasena FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::NUMERO_CONTROL . "=?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $numeroControl);
            $sentencia->execute();

            if ($sentencia) {
                $resultado = $sentencia->fetch();

                if (self::validarContrasena($contrasena, $resultado['contrasena'])) {
                    return true;
                } else
                    return false;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    private static function validarContrasena($contrasenaPlana, $contrasenaHash)
    {
        return password_verify($contrasenaPlana, $contrasenaHash);
    }

    private static function obtenerAlumnoPorNumeroControl($numeroControl)
    {
        $comando = "SELECT " .
            self::NOMBRE_COMPLETO . "," .
            self::NUMERO_CONTROL . "," .
            self::CLAVE_API . "," .
            self::CARRERA_ID . "," .
            self::SEMESTRE .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::NUMERO_CONTROL . "=?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
        $sentencia->bindParam(1, $numeroControl);

        if ($sentencia->execute())
            return $sentencia->fetch(PDO::FETCH_ASSOC);
        else
            return null;
    }

    public static function autorizar()
    {
        $cabeceras = apache_request_headers();

        if (isset($cabeceras["Authorization"])) {
            $claveApi = $cabeceras["Authorization"];

            //Si viene en formato "Berear <token>", extraer solo el token
            if (stripos($claveApi, 'Bearer') === 0) {
                $claveApi = trim(substr($claveApi, 7));
            }

            if (Alumno::validarClaveApi($claveApi)) {
                return Alumno::obtenerIdAlumno($claveApi);
            } else {
                throw new ExcepcionApi(self::ESTADO_CLAVE_NO_AUTORIZADA, "Clave Api no Autorizada", 401);
            }
        } else {
            throw new ExcepcionApi(
                self::ESTADO_AUSENCIA_CLAVE_API,
                ("Se requiere Clave Api para autentificacion")
            );
        }
    }

    private static function validarClaveApi($claveApi)
    {
        $comando = "SELECT COUNT(" . self::ID . ")" .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::CLAVE_API . "=?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
        $sentencia->bindParam(1, $claveApi);
        $sentencia->execute();

        return $sentencia->fetchColumn(0) > 0;
    }

    private static function obtenerIdAlumno($claveApi)
    {
        $comando = "SELECT " . self::ID .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::CLAVE_API . "=?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
        $sentencia->bindParam(1, $claveApi);

        if ($sentencia->execute()) {
            $resultado = $sentencia->fetch();
            return $resultado['id'];
        } else {
            return null;
        }
    }

    //Peticion GET
    public static function get($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            $parameters = array_filter($parameters, function ($value) {
                return $value !== '';
            });

            if (empty($parameters)) {
                return self::getAll();
            } else if (count($parameters) == 1) {
                return self::getId($parameters[0]);
            } else if (count($parameters) == 2) {
                return self::getMany($parameters[0], $parameters[1]);
            } else {
                throw new ExcepcionApi(self::ESTADO_URL_INCORRECTA, "URL incorrecta! ");
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
        }
    }

    private static function getAll()
    {
        try {
            $comando = "SELECT id, nombreCompleto, numeroControl, carreraId, semestre FROM " . self::NOMBRE_TABLA;
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);

            if ($sentencia->execute()) {
                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXITO,
                    "alumnos" => $sentencia->fetchAll(PDO::FETCH_ASSOC)
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener los Alumnos");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function getId($id)
    {
        try {
            $comando = "SELECT id, nombreCompleto, numeroControl, carreraId, semestre FROM " . self::NOMBRE_TABLA . " WHERE " . self::ID . "=?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $id, PDO::PARAM_INT);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);

                if (empty($resultado)) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe un alumno con el ID especificado");
                }

                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXITO,
                    "alumno" => $resultado
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener el Alumno");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function getMany($idIni, $idFin)
    {
        try {
            $comando = "SELECT id, nombreCompleto, numeroControl, carreraId, semestre FROM "
                . self::NOMBRE_TABLA . " WHERE "
                . self::ID . " BETWEEN ? AND ?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idIni, PDO::PARAM_INT);
            $sentencia->bindParam(2, $idFin, PDO::PARAM_INT);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);

                if (empty($resultado)) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existen alumnos en el rango de IDs especificado");
                }

                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXITO,
                    "alumnos" => $resultado
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener los Alumnos");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    //Peticion PUT
    public static function put($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            if (empty($parameters)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Se requiere el ID del alumno a actualizar");
            }

            $id = $parameters[0];
            if (!self::existeAlumno($id)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe un alumno con el ID especificado");
            }

            $cuerpo = file_get_contents('php://input');
            $alumno = json_decode($cuerpo);

            if (self::actualizarAlumno($id, $alumno)) {
                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXITO,
                    "mensaje" => "Alumno actualizado correctamente"
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al actualizar el alumno");
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
        }
    }

    private static function existeAlumno($id)
    {
        try {
            $comando = "SELECT COUNT(*) FROM " . self::NOMBRE_TABLA . " WHERE " . self::ID . "=?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $id, PDO::PARAM_INT);
            $sentencia->execute();
            return $sentencia->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    private static function actualizarAlumno($id, $datosAlumno)
    {
        try {
            $campos = [];
            $valores = [];
            $tipos = [];

            // Verificar y agregar cada campo si existe
            if (isset($datosAlumno->nombreCompleto)) {
                $campos[] = self::NOMBRE_COMPLETO . "=?";
                $valores[] = $datosAlumno->nombreCompleto;
                $tipos[] = PDO::PARAM_STR;
            }
            if (isset($datosAlumno->numeroControl)) {
                $campos[] = self::NUMERO_CONTROL . "=?";
                $valores[] = $datosAlumno->numeroControl;
                $tipos[] = PDO::PARAM_STR;
            }
            if (isset($datosAlumno->contrasena)) {
                $campos[] = self::CONTRASENA . "=?";
                $valores[] = self::encriptarContrasena($datosAlumno->contrasena);
                $tipos[] = PDO::PARAM_STR;
            }
            if (isset($datosAlumno->carreraId)) {
                $campos[] = self::CARRERA_ID . "=?";
                $valores[] = $datosAlumno->carreraId;
                $tipos[] = PDO::PARAM_INT;
            }
            if (isset($datosAlumno->semestre)) {
                $campos[] = self::SEMESTRE . "=?";
                $valores[] = $datosAlumno->semestre;
                $tipos[] = PDO::PARAM_INT;
            }

            if (empty($campos)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No se proporcionaron datos para actualizar");
            }

            // Construir la consulta SQL
            $consulta = "UPDATE " . self::NOMBRE_TABLA . " SET " . implode(", ", $campos) . " WHERE " . self::ID . "=?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($consulta);

            // Vincular los valores dinámicamente
            foreach ($valores as $i => $valor) {
                $sentencia->bindParam($i + 1, $valores[$i], $tipos[$i]);
            }
            // Vincular el ID al último parámetro
            $sentencia->bindParam(count($valores) + 1, $id, PDO::PARAM_INT);

            // Ejecutar y verificar si se realizó la actualización
            $sentencia->execute();
            return $sentencia->rowCount() > 0;

        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    //Peticion DELETE
    public static function delete($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            if (empty($parameters)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Se requiere el ID del alumno a eliminar");
            }

            $id = $parameters[0];
            if (!self::existeAlumno($id)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe un alumno con el ID especificado");
            }

            if (self::eliminarAlumno($id)) {
                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXITO,
                    "mensaje" => "Alumno eliminado correctamente"
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al actualizar el alumno");
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
        }
    }

    private static function eliminarAlumno($id)
    {
        try {
            $consulta = "DELETE FROM " . self::NOMBRE_TABLA . " WHERE " . self::ID . "=?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($consulta);
            $sentencia->bindParam(1, $id, PDO::PARAM_INT);
            $sentencia->execute();
            return $sentencia->rowCount() > 0;
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }
}
?>