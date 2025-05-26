<?php
require_once './datos/ConexionBD.php';
require_once 'Alumno.php';

class Carrera
{
    //Datos de la tabla "Carrera"
    const NOMBRE_TABLA = "carreras";
    const CLAVE = "clave";
    const NOMBRE = "nombre";
    const NIVEL_ID = "nivelId";

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
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existen carreras");
                }

                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                        "carreras" => $resultado
                    ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener las carreras");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    private static function getId($clave)
    {
        try {
            $comando = "SELECT * FROM " . self::NOMBRE_TABLA . " WHERE " . self::CLAVE . "=?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $clave, PDO::PARAM_INT);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);

                if (empty($resultado)) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe la carrera con el ID especidicado");
                }

                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                        "carrera" => $resultado
                    ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener la carrera");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function getMany($claveIni, $claveFin)
    {
        try {
            $comando = "SELECT * FROM " .
                self::NOMBRE_TABLA . " WHERE " .
                self::CLAVE . " BETWEEN ? AND ?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $claveIni, PDO::PARAM_INT);
            $sentencia->bindParam(2, $claveFin, PDO::PARAM_INT);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);

                if (empty($resultado)) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No exiten carreras en el rango de IDs especificados");
                }

                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                        "carreras" => $resultado
                    ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Se ha producido un error al intentar obtener carreras");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    //Peticion POST
    public static function post($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (!isset($idAlumno)) {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave Api");
        }

        // Manejar la petición de reporte
        if (count($parameters) == 1 && $parameters[0] === 'reporte') {
            return self::reporteCarreras();
        }

        // Lógica existente para el registro de carrera
        if (empty($parameters) || $parameters[0] !== 'registro') {
            throw new ExcepcionApi(self::ESTADO_URL_INCORRECTA, "Url mal formada", 400);
        }

        $body = file_get_contents('php://input');
        $carrera = json_decode($body);

        if (empty($carrera->nombre) || empty($carrera->nivelId)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "El nombre y clave de carrera es requerido");
        }

        if (self::crearCarrera($carrera)) {
            http_response_code(200);
            return
                [
                    "estado" => self::ESTADO_CREACION_EXITOSA,
                    "mensaje" => "Carrera creado correctamente"
                ];
        } else {
            throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al crear carrera");
        }
    }

    private static function crearCarrera($datosCarrera)
    {
        try {
            if (isset($datosCarrera)) {
                $nombre = $datosCarrera->nombre;
                $nivelId = $datosCarrera->nivelId;

                $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                    self::NOMBRE . "," .
                    self::NIVEL_ID . ")" .
                    " VALUES(?,?)";

                $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
                $sentencia->bindParam(1, $nombre);
                $sentencia->bindParam(2, $nivelId, PDO::PARAM_INT);

                if ($sentencia->execute()) {
                    return true;
                } else {
                    return false;
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al crear carrera");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    //Peticion PUT
    public static function put($parameters)
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            if (empty($parameters)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Se requiere el ID de carrera a actualizar", 7);
            }

            $clave = $parameters[0];
            if (!self::existenciaCarrera($clave)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe una carrera con el ID especificado");
            }

            $body = file_get_contents('php://input');
            $carrera = json_decode($body);

            if (self::actualizarCarrera($clave, $carrera)) {
                http_response_code(200);
                return
                    [
                        "estado" => self::ESTADO_CREACION_EXITOSA,
                        "mensaje" => "Carrera actualizada exitosamente!"
                    ];
            }
        }
    }

    private static function existenciaCarrera($clave)
    {
        try {
            $comando = "SELECT COUNT(*) FROM " . self::NOMBRE_TABLA . " WHERE " . self::CLAVE . "=?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $clave, PDO::PARAM_INT);
            $sentencia->execute();
            return $sentencia->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    private static function actualizarCarrera($clave, $carrera)
    {
        try {
            $campos = [];
            $valores = [];
            $tipos = [];

            // Verificar y agregar cada campo si existe
            if (isset($carrera->nombre)) {
                $campos[] = self::NOMBRE . "=?";
                $valores[] = $carrera->nombre;
                $tipos[] = PDO::PARAM_STR;
            }
            if (isset($carrera->nivelId)) {
                $campos[] = self::NIVEL_ID . "=?";
                $valores[] = $carrera->nivelId;
                $tipos[] = PDO::PARAM_INT;
            }

            if (empty($campos)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No se proporcionaron datos para actualizar");
            }

            // Construir la consulta SQL
            $consulta = "UPDATE " . self::NOMBRE_TABLA . " SET " . implode(", ", $campos) . " WHERE " . self::CLAVE . "=?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($consulta);

            // Vincular los valores dinámicamente
            foreach ($valores as $i => $valor) {
                $sentencia->bindParam($i + 1, $valores[$i], $tipos[$i]);
            }
            // Vincular la clave al último parámetro
            $sentencia->bindParam(count($valores) + 1, $clave, PDO::PARAM_INT);

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
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Se requiere el ID de la carrera a eliminar");
            }

            $clave = $parameters[0];
            if (!self::existenciaCarrera($clave)) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No existe una carrera con el ID especificado");
            }

            if (self::eliminarCarrera($clave)) {
                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXISTENCIA_RECURSO,
                    "mensaje" => "Carrera eliminada exitosamente!"
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al eliminar la carrera");
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
        }
    }

    private static function eliminarCarrera($clave)
    {
        try {
            $consulta = "DELETE FROM " . self::NOMBRE_TABLA . " WHERE " . self::CLAVE . "=?";
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($consulta);
            $sentencia->bindParam(1, $clave, PDO::PARAM_INT);
            $sentencia->execute();
            return $sentencia->rowCount() > 0;
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    private static function reporteCarreras()
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            // Definir el título del reporte para carreras
            $titulo = "Reporte de Carreras";

            // Configurar las cabeceras para indicar que se enviará un PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="reporte_carreras.pdf"');

            // Incluir la vista que generará el PDF. La vista leerá los datos JSON del cuerpo de la petición POST.
            require_once './vistas/reporteJsonGenerico.php';

            return true; // Indicar éxito

        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
        }
    }
}
?>