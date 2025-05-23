<?php
require_once './datos/ConexionBD.php';
require_once 'Alumno.php';

class NivelesCarrera
{
    //Datos de la tabla "Niveles Carrera"
    const NOMBRE_TABLA = "nivelescarrera";
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

    public static function get()
    {
        $idAlumno = Alumno::autorizar();

        return [
            "estado" => self::ESTADO_CREACION_EXITOSA,
            "idAlumno" => $idAlumno
        ];
    }

    public static function post()
    {
        $idAlumno = Alumno::autorizar();

        if (isset($idAlumno)) {
            $body = file_get_contents('php://input');
            $nivelCarrera = json_decode($body);

            if (self::crearNivelesCarrera($nivelCarrera)) {
                return [
                    "estado" => self::ESTADO_CREACION_EXITOSA,
                    "mensaje" => "Nivel carrera creado correctamente"
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al crear nivel carrera");
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave Api");
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
}

?>