<?php
require_once './datos/ConexionBD.php';
require_once 'Alumno.php';
class Asistencia{
    const NOMBRE_TABLA = "Horarios";
    const ID = "id";
    const FECHA = "fecha";
    const HORARIO = "hora";
    const ALUMNO_ID = "alumnoId";
    const ID_TIPO_INCIDENCIA = "tipoIncidenciaId";
}


/* Final de la clase de asistencia */
?>