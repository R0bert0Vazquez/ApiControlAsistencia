<?php
require_once './datos/ConexionBD.php';
require_once 'Alumno.php';

class NivelesCarrera
{
    //Datos de la tabla "Niveles Carrera"
    const NOMBRE_TABLA = "nivelescarrera";
    const ID = "id";
    const NOMBRE = "nombre";

    //Constantes de estado para respuestas y errores
    const ESTADO_URL_INCORRECTA = 1;
    const ESTADO_CREACION_EXITOSA = 2;
    const ESTADO_CREACION_FALLIDA = 3;
    const ESTADO_FALLA_DESCONOCIDO = 4;
    const ESTADO_ERROR_BD = 5;
    const ESTADO_PARAMETROS_INCORRECTOS = 7;
    const ESTADO_CLAVE_NO_AUTORIZADA = 8;
    const ESTADO_AUSENCIA_CLAVE_API = 9;
    const ESTADO_EXISTENCIA_RECURSO = 2;

    //Peticiones GET
    public static function get($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            // Filtrar parámetros vacíos
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
                throw new ExcepcionApi(self::ESTADO_URL_INCORRECTA, "URL incorrecta");
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave Api");
        }
    }

    private static function getAll()
    {
        try {
            $comando = "SELECT * FROM " . self::NOMBRE_TABLA;
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);

            if ($sentencia->execute()) {
                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                        "niveles carrera" => $sentencia->fetchAll(PDO::FETCH_ASSOC)
                    ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener los niveles carrera");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    private static function getId($id)
    {
        try {
            $comando = "SELECT * FROM " . self::NOMBRE_TABLA . " WHERE " . self::ID . "=?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $id, PDO::PARAM_INT);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);

                if (empty($resultado)) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe nivel carrera con el ID especidicado");
                }

                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                        "nivel carrera" => $resultado
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
            $comando = "SELECT * FROM " .
                self::NOMBRE_TABLA . " WHERE " .
                self::ID . " BETWEEN ? AND ?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idIni, PDO::PARAM_INT);
            $sentencia->bindParam(2, $idFin, PDO::PARAM_INT);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);

                if (empty($resultado)) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No exiten niveles carrera en el rango de IDs especificados");
                }

                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                    "niveles carrera" => $resultado
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener niveles carrera");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    //Peticiones POST
    public static function post($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (!isset($idAlumno)) {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave Api");
        }

        if (empty($parameters) || $parameters[0] !== 'registro') {
            throw new ExcepcionApi(self::ESTADO_URL_INCORRECTA, "Url mal formada", 400);
        }

        $body = file_get_contents('php://input');
        $nivelCarrera = json_decode($body);

        if (!isset($nivelCarrera->nombre) || empty($nivelCarrera->nombre)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "El nombre del nivel carrera es requerido");
        }

        if (self::crearNivelesCarrera($nivelCarrera)) {
            http_response_code(200);
            return [
                "estado" => self::ESTADO_CREACION_EXITOSA,
                "mensaje" => "Nivel carrera creado correctamente"
            ];
        } else {
            throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al crear nivel carrera");
        }
    }

    private static function crearNivelesCarrera($datosNivelCarrera)
    {
        try {
            if (isset($datosNivelCarrera)) {
                $nombre = $datosNivelCarrera->nombre;

                $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                    self::NOMBRE . ")" .
                    " VALUES(?)";

                $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
                $sentencia->bindParam(1, $nombre);

                if ($sentencia->execute()) {
                    return true;
                } else {
                    return false;
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al crear nivel carrera");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    //Peticiones PUT
    public static function put($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            if (empty($parameters)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Se requiere el ID de nivel carrera a actualizar");
            }

            $id = $parameters[0];
            if (!self::existeNivelCarrera($id)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe un nivel carrera con el ID especificado");
            }

            $body = file_get_contents('php://input');
            $nivelCarrera = json_decode($body);

            if (self::actualizarNivelCarrera($id, $nivelCarrera)) {
                http_response_code(200);

            }
        }
    }

    private static function existeNivelCarrera($id)
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

    private static function actualizarNivelCarrera($id, $datosNivelCarrera)
    {
        try {
            $campos = [];
            $valores = [];
            $tipos = [];

            // Verificar y agregar cada campo si existe
            if (isset($datosNivelCarrera->nombre)) {
                $campos[] = self::NOMBRE . "=?";
                $valores[] = $datosNivelCarrera->nombre;
                $tipos[] = PDO::PARAM_STR;
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

    //Peticion Delete
    public static function delete($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            if (empty($parameters)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Se requiere el ID del nivel carrera a eliminar");
            }

            $id = $parameters[0];
            if (!self::existeNivelCarrera($id)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe un nivel carrera con el ID especificado");
            }

            if (self::eliminarNivelCarrera($id)) {
                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                    "mensaje" => "Nivel carrera eliminado correctamente"
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al eliminar el nivel carrera");
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
        }
    }

    private static function eliminarNivelCarrera($id)
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