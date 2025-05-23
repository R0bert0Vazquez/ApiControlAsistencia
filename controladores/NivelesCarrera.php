<?php
require_once './datos/ConexionBD.php';
require_once 'Alumno.php';

class NivelesCarrera
{
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
        $body = file_get_contents('php://input');
        $nivelCarrera = json_decode($body);

        $idNivelCarrera = NivelesCarrera::crearNivelesCarrera($idAlumno, $nivelCarrera);

        http_response_code(201);
        return [
            "estado" => self::ESTADO_CREACION_EXITOSA,
            "mensaje" => "Nivel Carrera -> Creado con exito!",
            "Nivel Carrera" => $idNivelCarrera
        ];
    }

    private static function crearNivelesCarrera($idAlumno, $nivelCarrera)
    {
        if (isset($idAlumno, $nivelCarrera)) {
            return [
                "IdAlumno" => $idAlumno,
                "Nivel Carrera" => $nivelCarrera
            ];
        }

    }
}

?>