<?php
require_once './datos/ConexionBD.php';
require_once 'Alumno.php';

class horario
{
    //Constantes de estado para respuestas y errores
    const NOMBRE_TABLA = "Horarios";
    const ID = "id";
    const   ALUMNO_ID  = "alumnoId";
    const DIA_SEMANA = "diaSemana";
    const ENTRADA_1 = "entrada1";
    const SALIDA_1 = "salida1";
    const ENTRADA_2 = "entrada2";
    const SALIDA_2 = "salida2";

    const ESTADO_URL_INCORRECTA = 1;
    const ESTADO_CREACION_EXITOSA = 2;
    const ESTADO_CREACION_FALLIDA = 3;
    const ESTADO_FALLA_DESCONOCIDO = 4;
    const ESTADO_ERROR_BD = 5;
    const ESTADO_PARAMETROS_INCORRECTOS = 7;
    const ESTADO_CLAVE_NO_AUTORIZADA = 8;
    const ESTADO_AUSENCIA_CLAVE_API = 9;



    public static function get($params = null){
        $idAlumno = Alumno::autorizar();
        if($idAlumno){
            if(count($params) >= 2){
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "NUMERO DE PARAMETROS INVALIDOS", 400);    
            }else{
                return $params? self::getById($params) : self::getAll();
            }
            
        }else
            throw new ExcepcionApi(self::ESTADO_CLAVE_NO_AUTORIZADA, "Sin Accesso", 401);
    }

    private static function getAll(){
            return self::obtenerHorario();
    }
    private static function getById($params){
        try{
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare('SELECT id FROM Alumnos');
            $arrayId = $sentencia->execute()?$sentencia->fetchAll(PDO::FETCH_ASSOC):null;
            $soloId = array_column($arrayId,'id');
            if(in_array($params[0],$soloId)){
                // $qry = 'SELECT * FROM '. self::NOMBRE_TABLA ." WHERE ". self::ALUMNO_ID ."=?";
                // $sentencia2 = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($qry);
                // $sentencia2->bindParam(1,$params[0]);
                // $sentencia2->execute();  
                $resp = self::obtenerHorario($params[0]);
                return $resp;

            }else{
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, ("Parametro no Encontrado".$params[0]));
            }
        }catch(PDOException $e){
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }
    private static function obtenerHorario($params = null){
        try {
            $qry = 'SELECT * FROM '. self::NOMBRE_TABLA;
            if($params){
                $qry .= " WHERE ". self::ALUMNO_ID ."=?";
                $sentencia2 = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($qry);
                $sentencia2->bindParam(1,$params);
            }else {
                $sentencia2 = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($qry);
            }
            return $sentencia2->execute()?$sentencia2->fetchAll(PDO::FETCH_ASSOC):null;
        } catch(PDOException $e){
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }


    public static function post($peticion = null) {
        $idAlumno = Alumno::autorizar();
        if($idAlumno){
            if($peticion){
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No requiere", 400);
            }else{
                $body = json_decode(file_get_contents('php://input'));
                return ['estado'=>self::crearHorario($idAlumno,$body),'mensaje'=>"Horario Ingresado con Exito"];
            }
        }

        
    }

    private static function crearHorario($idAlumno, $body) {
        try {
            $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

            //Sentencia INSERT
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                self::ALUMNO_ID. "," .
                self::DIA_SEMANA. "," .
                self::ENTRADA_1 . "," .
                self::SALIDA_1 . "," .
                self::ENTRADA_2 . "," .
                self::SALIDA_2 . ")" .
                " VALUES(?,?,?,?,?,?)";

            $sentencia = $pdo->prepare($comando);

            $sentencia->bindParam(1, $idAlumno);
            $sentencia->bindParam(2, $body->diaSemana);
            $sentencia->bindParam(3, $body->entrada1);
            $sentencia->bindParam(4, $body->salida1);
            $sentencia->bindParam(5, $body->entrada2);
            $sentencia->bindParam(6, $body->salida2);

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

    public static function put($params = null){
        $body = json_decode(file_get_contents("php://input"));
        $idUser = Alumno::autorizar();
        if(!$body){
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Argumentos Faltantes");
        }else{
            return self::update($idUser,$body);
        }

        
    }

    private static function update($idUser,$body){
        try {
            if($idUser){
                $db = ConexionBD::obtenerInstancia()->obtenerBD();
                $qry = 'UPDATE '.self::NOMBRE_TABLA.' SET '.
                self::ENTRADA_1.' = ?,'.
                self::SALIDA_1.' = ?,'.
                self::ENTRADA_2.'= ?,'.
                self::SALIDA_2.' = ?'.
                ' WHERE '.self::ALUMNO_ID.' ='.$idUser . ' AND '.self::DIA_SEMANA.' =?';

                $sentencia = $db->prepare($qry);
                $sentencia->bindParam(1,$body->entrada1);
                $sentencia->bindParam(2,$body->salida1);
                $sentencia->bindParam(3,$body->entrada2);
                $sentencia->bindParam(4,$body->salida2);
                $sentencia->bindParam(5,$body->diaSemana);
                

                return $sentencia->execute()?['estado'=>self::ESTADO_CREACION_EXITOSA,'mensaje'=>"Realizado Correctamente"]:['estado'=>self::ESTADO_ERROR_BD,'mensaje'=>"Erro Intentelo de Nuevo"];
            }else 
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Id no Encontrado");

        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    public static function delete($params = null){
        $idUser = Alumno::autorizar();
        if(!$params){
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Parametros Faltantes");
        }else{
            if(count($params)>= 2)
                return ['estado'=>self::ESTADO_PARAMETROS_INCORRECTOS,'mensaje'=>"Faltan parametros para la peticion".count($params)." numero Parametro"];
            else
                return self::deleteBD($idUser,$params);
        }
    }

    private static function deleteBD($idUser,$params){
        try {
                $db = ConexionBD::obtenerInstancia()->obtenerBD();
                $qry = 'DELETE FROM '.self::NOMBRE_TABLA.
                ' WHERE '.self::ALUMNO_ID.' = '.$idUser. ' AND '.self::DIA_SEMANA.' = ?';

                $sentencia = $db->prepare($qry);
                $sentencia->bindParam(1,$params[0]);

                return $sentencia->execute()?['estado'=>self::ESTADO_CREACION_EXITOSA,'mensaje'=>"Realizado Correctamente"]:['estado'=>self::ESTADO_ERROR_BD,'mensaje'=>"Error Intentelo de Nuevo"];
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }
}



?>