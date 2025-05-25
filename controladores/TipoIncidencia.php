<?php
    require_once './datos/ConexionBD.php';
    require_once 'Alumno.php';


    class TipoIncidencia{
        const NOMBRE_TABLA = "TiposIncidencia";
        const ID = "id";
        const NOMBRE = "nombre";

        
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
                return $params? self::getByName($params) : self::getAll();
            }
            
        }else
            throw new ExcepcionApi(self::ESTADO_CLAVE_NO_AUTORIZADA, "Sin Accesso", 401);
    }

    private static function getAll(){
            return self::obtenerTipoIncidencia();
    }
    private static function getByName($params){
        try{
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare('SELECT nombre FROM TiposIncidencia');
            $arrayName = $sentencia->execute()?$sentencia->fetchAll(PDO::FETCH_ASSOC):null;
            $soloName = array_column($arrayName,'nombre');
            if(in_array($params[0],$soloName)){
                return self::obtenerTipoIncidencia($params[0]);
            }else{
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, ("Parametro no Encontrado".$params[0]));
            }
        }catch(PDOException $e){
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }
    private static function obtenerTipoIncidencia($params = null){
        try {
            $db = ConexionBD::obtenerInstancia()->obtenerBD();
            $qry = 'SELECT * FROM '. self::NOMBRE_TABLA;
            if($params){
                $qry .= " WHERE ". self::NOMBRE ."=?";
                $sentencia2 = $db->prepare($qry);
                $sentencia2->bindParam(1,$params);
            }else {
                $sentencia2 = $db->prepare($qry);
            }
            return $sentencia2->execute()?$sentencia2->fetchAll(PDO::FETCH_ASSOC):null;
        } catch(PDOException $e){
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }
    public static function post($peticion = null) {
        $idAlumno = Alumno::autorizar();
        if($idAlumno){
            $body = json_decode(file_get_contents('php://input'));
            if(!$body){
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Se requieren body", 400);
            }else{
                return ['estado'=>self::crearHorario($body->nombre),'mensaje'=>"Horario Ingresado con Exito"];
            }
        }

        
    }
    private static function crearHorario($newIncidencia) {
        try {
            $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

            //Sentencia INSERT
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                self::NOMBRE.')'.
                " VALUES(?);";

            $sentencia = $pdo->prepare($comando);
            $sentencia->bindParam(1, $newIncidencia);

            $resultado = $sentencia->execute();

            if ($resultado) {
                return self::ESTADO_CREACION_EXITOSA;
            } else {
                return self::ESTADO_CREACION_FALLIDA;
            }

        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage().$comando);
        }
    }
    public static function put($params = null){
        $body = json_decode(file_get_contents("php://input"));
        $idUser = Alumno::autorizar();
        if(!$body){
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Argumentos Faltantes");
        }else{
            return self::update($body);
        }

        
    }

    private static function update($body){
        try {
            if($body){
                $db = ConexionBD::obtenerInstancia()->obtenerBD();
                $qry = 'UPDATE '.self::NOMBRE_TABLA.' SET '.
                self::NOMBRE.' = ?'.
                ' WHERE '.self::NOMBRE.' = \''.$body->nombre.'\'';

                $sentencia = $db->prepare($qry);
                $sentencia->bindParam(1,$body->newValue);
                

                return $sentencia->execute()?['estado'=>self::ESTADO_CREACION_EXITOSA,'mensaje'=>"Realizado Correctamente"]:['estado'=>self::ESTADO_ERROR_BD,'mensaje'=>"Erro Intentelo de Nuevo"];
            }else 
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Id no Encontrado");

        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage().$qry);
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
                return self::deleteBD($params);
        }
    }

    private static function deleteBD($params){
        try {
                $db = ConexionBD::obtenerInstancia()->obtenerBD();
                $qry = 'DELETE FROM '.self::NOMBRE_TABLA.
                ' WHERE '.self::NOMBRE.' = ?';

                $sentencia = $db->prepare($qry);
                $sentencia->bindParam(1,$params[0]);

                return $sentencia->execute()?['estado'=>self::ESTADO_CREACION_EXITOSA,'mensaje'=>"Realizado Correctamente"]:['estado'=>self::ESTADO_ERROR_BD,'mensaje'=>"Error Intentelo de Nuevo"];
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }
    

}


?>