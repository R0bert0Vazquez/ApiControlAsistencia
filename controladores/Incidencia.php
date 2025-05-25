<?php
require_once './datos/ConexionBD.php';
require_once 'Alumno.php';

class Incidencia
{
    //Datos de la tabla "Incidencia"
    const NOMBRE_TABLA = "incidencias";
    const ID = "id";
    const FECHA = "fecha";
    const ALUMNO_ID = "alumnoId";
    const TIPO_INCIDENCIA_ID = "tipoIncidenciaId";
    const HORA_ENTRADA = "horaEntrada";
    const HORA_SALIDA = "horaSalida";

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
                $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);

                if (empty($resultado)) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existen incidencias");
                }

                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                        "incidencias" => $resultado
                    ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener las incidencias");
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
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe la incidencia con el ID especidicado");
                }

                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                        "incidencia" => $resultado
                    ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener la incidencia");
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
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No exiten incidencias en el rango de IDs especificados");
                }

                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                        "incidencias" => $resultado
                    ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener incidencias");
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
        $incidencia = json_decode($body);

        if (empty($incidencia->fecha) || empty($incidencia->alumnoId) || empty($incidencia->tipoIncidenciaId) /*|| empty($incidencia->horaEntrada) || empty($incidencia->horaSalida)*/) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta proporcionar un dato que es requerido");
        }

        if (self::crearIncidencia($incidencia)) {
            http_response_code(200);
            return
                [
                    "estado" => self::ESTADO_CREACION_EXITOSA,
                    "mensaje" => "Incidencia creado correctamente"
                ];
        } else {
            throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al crear incidencia");
        }
    }

    private static function crearIncidencia($datosIncidencia)
    {
        try {
            if (isset($datosIncidencia)) {
                $fecha = $datosIncidencia->fecha;
                $alumnoId = $datosIncidencia->alumnoId;
                $tipoIncidenciaId = $datosIncidencia->tipoIncidenciaId;
                $horaEntrada = $datosIncidencia->horaEntrada;
                $horaSalida = $datosIncidencia->horaSalida;

                $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                    self::FECHA . "," .
                    self::ALUMNO_ID . "," .
                    self::TIPO_INCIDENCIA_ID . "," .
                    self::HORA_ENTRADA . "," .
                    self::HORA_SALIDA . ") " .
                    " VALUES(?,?,?,?,?)";

                $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
                $sentencia->bindParam(1, $fecha, PDO::PARAM_STR);
                $sentencia->bindParam(2, $alumnoId, PDO::PARAM_INT);
                $sentencia->bindParam(3, $tipoIncidenciaId, PDO::PARAM_INT);
                $sentencia->bindParam(4, $horaEntrada, PDO::PARAM_STR);
                $sentencia->bindParam(5, $horaSalida, PDO::PARAM_STR);

                if ($sentencia->execute()) {
                    return true;
                } else {
                    return false;
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al crear incidencia");
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
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Se requiere el ID de la incidencia a actualizar", 7);
            }

            $id = $parameters[0];
            if (!self::existenciaIncidencia($id)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe una incidencia con el ID especificado");
            }

            $body = file_get_contents('php://input');
            $incidencia = json_decode($body);

            if (self::actualizarIncidencia($id, $incidencia)) {
                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_CREACION_EXITOSA,
                        "mensaje" => "Incidencia actualizada exitosamente!"
                    ];
            }
        }
    }

    private static function existenciaIncidencia($id)
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

    private static function actualizarIncidencia($id, $incidencia)
    {
        try {
            $campos = [];
            $valores = [];
            $tipos = [];

            // Verificar y agregar cada campo si existe
            if (isset($incidencia->fecha)) {
                $campos[] = self::FECHA . "=?";
                $valores[] = $incidencia->fecha;
                $tipos[] = PDO::PARAM_STR;
            }
            if (isset($incidencia->alumnoId)) {
                $campos[] = self::ALUMNO_ID . "=?";
                $valores[] = $incidencia->alumnoId;
                $tipos[] = PDO::PARAM_INT;
            }
            if (isset($incidencia->tipoIncidenciaId)) {
                $campos[] = self::TIPO_INCIDENCIA_ID . "=?";
                $valores[] = $incidencia->tipoIncidenciaId;
                $tipos[] = PDO::PARAM_INT;
            }
            if (isset($incidencia->horaEntrada)) {
                $campos[] = self::HORA_ENTRADA . "=?";
                $valores[] = $incidencia->horaEntrada;
                $tipos[] = PDO::PARAM_STR;
            }
            if (isset($incidencia->horaSalida)) {
                $campos[] = self::HORA_SALIDA . "=?";
                $valores[] = $incidencia->horaSalida;
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

    //Peticiones DELETE
    public static function delete($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            if (empty($parameters)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Se requiere el ID de la incidencia a eliminar");
            }

            $id = $parameters[0];
            if (!self::existenciaIncidencia($id)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe una incidencia con el ID especificado");
            }

            if (self::eliminarIncidencia($id)) {
                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                    "mensaje" => "Incidencia eliminada exitosamente!"
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al eliminar la incidencia");
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
        }
    }

    private static function eliminarIncidencia($id)
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