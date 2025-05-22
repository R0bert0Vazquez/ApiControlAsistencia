<?php
require_once "./datos/ConexionBD.php";

class Alumno
{
    //Datos de la tabla "alumnos"
    const NOMBRE_TABLA = "alumnos";
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



    public static function post($peticion)
    {
        if ($peticion[0] == 'registro') {
            return self::registrarAlumno();
        } else if ($peticion[0] == 'login') {
            // return self::loguearAlumno();
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
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " ( " . 
            self::NOMBRE_COMPLETO . "," . 
            self::NUMERO_CONTROL . "," . 
            self::CONTRASENA . "," . 
            self::CLAVE_API . "," . 
            self::CARRERA_ID . "," . 
            self::SEMESTRE . "," .
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
}
?>