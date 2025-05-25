<?php
require_once './datos/ConexionBD.php';
require_once 'Alumno.php';
class Asistencia{
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

    public static function get($params = null){
        $idAlumno = Alumno::autorizar();
        if($idAlumno){
            if(count($params) >= 4){
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "NUMERO DE PARAMETROS INVALIDOS", 400);    
            }else{
                return $params? self::getByParams($params) : self::getAll();
            }
            
        }else
            throw new ExcepcionApi(self::ESTADO_CLAVE_NO_AUTORIZADA, "Sin Accesso", 401);
    }

    private static function getAll(){
            $res = self::obtenerAsistencia();
            return $res?['estado'=>self::ESTADO_CREACION_EXITOSA,'registroAsistencia'=>$res]:['estado'=>self::ESTADO_CREACION_FALLIDA,'mensaje'=>'fallido'];
    }
    private static function getByParams($params): array|null{
        $res = self::obtenerAsistencia($params);
        return $res?['estado'=>self::ESTADO_CREACION_EXITOSA,'registroAsistencia'=>$res]:['estado'=>self::ESTADO_CREACION_FALLIDA,'mensaje'=>'fallido'];
    }
    private static function obtenerAsistencia($params = null ){
        try {
            $arrayParmValid = ['idAlumno','fecha','tipoIncidencia','rangoFechas']; 
            $qry = 'SELECT * FROM '. self::NOMBRE_TABLA;
            $db = ConexionBD::obtenerInstancia()->obtenerBD();

            if($params == null){
                $sentencia=$db->prepare($qry);
                $sentencia->execute();
                return $sentencia->fetchAll(PDO::FETCH_ASSOC);
            }else{

            $filtro = $params[0];
            $params = array_slice($params,1);
            $params = array_values($params);

            if(!in_array($filtro, $arrayParmValid)){
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, 'filtroInvalido', 500);
            }else{
                switch ($filtro) {
                    case 'idAlumno':
                        $qry.= ' WHERE '.self::ALUMNO_ID.' = ?';
                        $sentencia = $db->prepare($qry);
                        $sentencia->bindParam(1,$params[0]);
                        break;
                    case 'fecha':
                        $date = DateTime::createFromFormat('Y-m-d', $params[0]);
                        if ($date && $date->format('Y-m-d') == $params[0]) {
                            $qry.= ' WHERE '. self::FECHA . ' = ?';
                            $sentencia = $db->prepare($qry);
                            $sentencia->bindParam(1,$params[0]);
                        }else
                            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, 'FORMATO FECHA INVALIDO', 500);
                        # code...
                        break;

                        case 'tipoIncidencia':
                            $qry.= ' WHERE '.self::ID_TIPO_INCIDENCIA .' = ?';
                            $sentencia = $db->prepare($qry);
                            $sentencia->bindParam(1,$params[0]);
                            break;

                        case 'rangoFechas':
                            if(count($params)<2){
                                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, 'FALTA DE PARAMETROS'.count($params), 500);
                            }else{
                                $date = DateTime::createFromFormat('Y-m-d', $params[0]);
                                $date2 = DateTime::createFromFormat('Y-m-d', $params[1]);

                                if ($date->format('Y-m-d') == $params[0] && $date2->format('Y-m-d') == $params[1]) {
                                    $qry.= ' WHERE '.self::FECHA .' BETWEEN ? AND ?';
                                    $sentencia = $db->prepare($qry);
                                    $sentencia->bindParam(1,$params[0]);
                                    $sentencia->bindParam(2,$params[1]);

                                }else
                                    throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, 'FORMATO FECHA INVALIDO', 500);
                                    
                            }
                            break;
                    default:
                        throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, 'ERRR', 500);
                        
                }
                
                return $sentencia->execute()?$sentencia->fetchAll(PDO::FETCH_ASSOC):null;
            }
            }

        } catch(PDOException $e){
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage().$qry, 500);
        }
    }

}



/* Final de la clase de asistencia */
?>