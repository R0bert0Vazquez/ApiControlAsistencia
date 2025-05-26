<?php
require_once './datos/ConexionBD.php';
require_once 'Alumno.php';
class Asistencia
{
    const NOMBRE_TABLA = "Asistencia";
    const ID = "id";
    const FECHA = "fecha";
    const HORARIO = "hora";
    const ALUMNO_ID = "alumnoId";
    const ID_TIPO_INCIDENCIA = "tipoIncidenciaId";
    const ESTADO_URL_INCORRECTA = 1;
    const ESTADO_CREACION_EXITOSA = 2;
    const ESTADO_CREACION_FALLIDA = 3;
    const ESTADO_FALLA_DESCONOCIDO = 4;
    const ESTADO_ERROR_BD = 5;
    const ESTADO_PARAMETROS_INCORRECTOS = 7;
    const ESTADO_CLAVE_NO_AUTORIZADA = 8;
    const ESTADO_AUSENCIA_CLAVE_API = 9;

    public static function get($params = null)
    {
        $idAlumno = Alumno::autorizar();
        if ($idAlumno) {
            if (count($params) >= 4) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "NUMERO DE PARAMETROS INVALIDOS", 400);
            } else {
                return $params ? self::getByParams($params) : self::getAll();
            }

        } else
            throw new ExcepcionApi(self::ESTADO_CLAVE_NO_AUTORIZADA, "Sin Accesso", 401);
    }

    private static function getAll()
    {
        $res = self::obtenerAsistencia();
        return $res ? ['estado' => self::ESTADO_CREACION_EXITOSA, 'registroAsistencia' => $res] : ['estado' => self::ESTADO_CREACION_FALLIDA, 'mensaje' => 'fallido'];
    }
    private static function getByParams($params): array|null
    {
        $res = self::obtenerAsistencia($params);
        return $res ? ['estado' => self::ESTADO_CREACION_EXITOSA, 'registroAsistencia' => $res] : ['estado' => self::ESTADO_CREACION_FALLIDA, 'mensaje' => 'fallido'];
    }
    private static function obtenerAsistencia($params = null)
    {
        try {
            $arrayParmValid = ['idAlumno', 'fecha', 'tipoIncidencia', 'rangoFechas'];
            $qry = 'SELECT * FROM ' . self::NOMBRE_TABLA;
            $db = ConexionBD::obtenerInstancia()->obtenerBD();

            if ($params == null) {
                $sentencia = $db->prepare($qry);
                $sentencia->execute();
                return $sentencia->fetchAll(PDO::FETCH_ASSOC);
            } else {

                $filtro = $params[0];
                $params = array_slice($params, 1);
                $params = array_values($params);

                if (!in_array($filtro, $arrayParmValid)) {
                    throw new ExcepcionApi(self::ESTADO_ERROR_BD, 'filtroInvalido', 500);
                } else {
                    switch ($filtro) {
                        case 'idAlumno':
                            $qry .= ' WHERE ' . self::ALUMNO_ID . ' = ?';
                            $sentencia = $db->prepare($qry);
                            $sentencia->bindParam(1, $params[0]);
                            break;
                        case 'fecha':
                            $date = DateTime::createFromFormat('Y-m-d', $params[0]);
                            if ($date && $date->format('Y-m-d') == $params[0]) {
                                $qry .= ' WHERE ' . self::FECHA . ' = ?';
                                $sentencia = $db->prepare($qry);
                                $sentencia->bindParam(1, $params[0]);
                            } else
                                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, 'FORMATO FECHA INVALIDO', 500);
                            # code...
                            break;

                        case 'tipoIncidencia':
                            $qry .= ' WHERE ' . self::ID_TIPO_INCIDENCIA . ' = ?';
                            $sentencia = $db->prepare($qry);
                            $sentencia->bindParam(1, $params[0]);
                            break;

                        case 'rangoFechas':
                            if (count($params) < 2) {
                                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, 'FALTA DE PARAMETROS' . count($params), 500);
                            } else {
                                $date = DateTime::createFromFormat('Y-m-d', $params[0]);
                                $date2 = DateTime::createFromFormat('Y-m-d', $params[1]);

                                if ($date->format('Y-m-d') == $params[0] && $date2->format('Y-m-d') == $params[1]) {
                                    $qry .= ' WHERE ' . self::FECHA . ' BETWEEN ? AND ?';
                                    $sentencia = $db->prepare($qry);
                                    $sentencia->bindParam(1, $params[0]);
                                    $sentencia->bindParam(2, $params[1]);

                                } else
                                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, 'FORMATO FECHA INVALIDO', 500);

                            }
                            break;
                        default:
                            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, 'ERRR', 500);

                    }

                    return $sentencia->execute() ? $sentencia->fetchAll(PDO::FETCH_ASSOC) : null;
                }
            }

        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage() . $qry, 500);
        }
    }
    public static function post($peticion = null)
    {
        $idAlumno = Alumno::autorizar();
        if ($idAlumno) {
            // Manejar la petición de reporte
            if ($peticion !== null && count($peticion) == 1 && $peticion[0] === 'reporte') {
                return self::reporteAsistencias();
            }

            // Lógica existente para la creación de asistencia
            if ($peticion) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No requiere parametros para la creación de asistencia", 400);
            } else {
                $body = json_decode(file_get_contents('php://input'));
                // Validar que se reciban los datos necesarios para crear asistencia
                if (empty($body->fecha) || empty($body->hora) || empty($body->tipoIncidenciaId)) {
                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Faltan datos para crear la asistencia (fecha, hora, tipoIncidenciaId)", 400);
                }
                return ['estado' => self::crearAsistencia($idAlumno, $body), 'mensaje' => "Asistencia Ingresada con Exito"];
            }
        }


    }

    private static function crearAsistencia($idAlumno, $body)
    {
        try {
            $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

            //Sentencia INSERT
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                self::FECHA . "," .
                self::HORARIO . "," .
                self::ALUMNO_ID . "," .
                self::ID_TIPO_INCIDENCIA . ")" .
                " VALUES(?,?,?,?);";

            $sentencia = $pdo->prepare($comando);

            $sentencia->bindParam(1, $body->fecha);
            $sentencia->bindParam(2, $body->hora);
            $sentencia->bindParam(3, $idAlumno);
            $sentencia->bindParam(4, $body->tipoIncidenciaId);

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
    public static function put($params = null)
    {
        $body = json_decode(file_get_contents("php://input"));
        $idUser = Alumno::autorizar();
        if (!$body) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Argumentos Faltantes");
        } else {
            return self::update($idUser, $body);
        }


    }

    private static function update($idUser, $body)
    {
        try {
            if ($idUser) {
                $db = ConexionBD::obtenerInstancia()->obtenerBD();
                $qry = 'UPDATE ' . self::NOMBRE_TABLA . ' SET ' .
                    self::HORARIO . ' = ?' .
                    ' WHERE ' . self::ALUMNO_ID . ' =' . $idUser . ' AND ' . self::FECHA . ' =?';

                $sentencia = $db->prepare($qry);
                $sentencia->bindParam(1, $body->newHora);
                $sentencia->bindParam(2, $body->fecha);


                return $sentencia->execute() ? ['estado' => self::ESTADO_CREACION_EXITOSA, 'mensaje' => "Realizado Correctamente"] : ['estado' => self::ESTADO_ERROR_BD, 'mensaje' => "Erro Intentelo de Nuevo"];
            } else
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Id no Encontrado");

        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }
    public static function delete($params = null)
    {
        $idUser = Alumno::autorizar();
        if (!$params) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Parametros Faltantes");
        } else {
            if (count($params) >= 2)
                return ['estado' => self::ESTADO_PARAMETROS_INCORRECTOS, 'mensaje' => "Faltan parametros para la peticion" . count($params) . " numero Parametro"];
            else
                return self::deleteBD($idUser, $params);
        }
    }

    private static function deleteBD($idUser, $params)
    {
        try {
            $db = ConexionBD::obtenerInstancia()->obtenerBD();
            $qry = 'DELETE FROM ' . self::NOMBRE_TABLA .
                ' WHERE ' . self::ALUMNO_ID . ' = ' . $idUser . ' AND ' . self::FECHA . ' = ?';

            $sentencia = $db->prepare($qry);
            $sentencia->bindParam(1, $params[0]);

            return $sentencia->execute() ? ['estado' => self::ESTADO_CREACION_EXITOSA, 'mensaje' => "Realizado Correctamente"] : ['estado' => self::ESTADO_ERROR_BD, 'mensaje' => "Error Intentelo de Nuevo"];
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    private static function reporteAsistencias()
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            // Definir el título del reporte para asistencias
            $titulo = "Reporte de Asistencias";

            // Configurar las cabeceras para indicar que se enviará un PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="reporte_asistencias.pdf"');

            // Incluir la vista que generará el PDF. La vista leerá los datos JSON del cuerpo de la petición POST.
            require_once './vistas/reporteJsonGenerico.php';

            return true; // Indicar éxito

        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave API");
        }
    }
}



/* Final de la clase de asistencia */
?>