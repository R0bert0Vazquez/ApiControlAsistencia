<?php
require_once './controladores/Alumno.php';
require_once './controladores/NivelesCarrera.php';
require_once './controladores/Horario.php';
require_once './controladores/Asistencia.php';
require_once './controladores/TipoIncidencia.php';

require_once './vistas/VistaJson.php';
require_once './vistas/VistaXML.php';
require_once './utilidades/ExcepcionApi.php';

// Constantes de estado
const ESTADO_URL_INCORRECTA = 2;
const ESTADO_EXISTENCIA_RECURSO = 3;
const ESTADO_METODO_NO_PERMITIDO = 4;

$vista = new VistaJson();

set_exception_handler(
    function ($exception) use ($vista) {
        $cuerpo = array(
            "estado" => $exception->estado,
            "mensaje" => $exception->getMessage()
        );
        if ($exception->getCode()) {
            $vista->estado = $exception->getCode();
        } else {
            $vista->estado = 500;
        }

        $vista->imprimir($cuerpo);
    }
);

// Validar error si no manda un recurso
if (!isset($_GET['PATH_INFO'])) {
    throw new ExcepcionApi(ESTADO_URL_INCORRECTA, ("No se reconoce la peticion"));
}

// Validar si mando el recurso
if (isset($_GET['PATH_INFO'])) {
    $parameters = explode('/', $_GET['PATH_INFO']);
    $recurso = $parameters[0];
    $parameters = array_slice($parameters, 1);
    $parameters = array_filter($parameters, function ($value) {
        return $value !== '';
    });
    $parameters = array_values($parameters);
    // echo "<br/>";
    // print_r($parameters);

    $recursos_existentes = array(
        'alumno',
        'nivelesCarrera',
        'horario', 
        'asistencia',
        'tipoIncidencia'
    );

    // Comprobar si existe el recurso
    if (!in_array($recurso, $recursos_existentes)) {
        throw new ExcepcionApi(
            ESTADO_EXISTENCIA_RECURSO,
            "No se reconoce el recurso al que intentas acceder"
        );
    }
} else {
    throw new ExcepcionApi(ESTADO_URL_INCORRECTA, ("No se reconoce la peticion"));

}

$request_method = strtolower($_SERVER['REQUEST_METHOD']);

$resultado = '';
$nombre_clase = $recurso;

$formato = isset($_GET['formato']) ? $_GET['formato'] : 'json';

switch ($formato) {
    case 'xml':
        $vista = new VistaXML();
        break;
    case 'json':
    default:
        $vista = new VistaJson();
}

// Filtrar método
switch ($request_method) {
    case 'get':
    case 'post':
    case 'put':
    case 'delete':
        if (method_exists($nombre_clase, $request_method)) {
            $respuesta = call_user_func(array($nombre_clase, $request_method), $parameters);
            $vista->estado = $respuesta?200:500;
            $vista->imprimir($respuesta);
            break;
        }
    default:
        $vista->estado = 405;
        $cuerpo = [
            "estado" => ESTADO_METODO_NO_PERMITIDO,
            "mensaje" => ("Método no permitido")
        ];
        $vista->imprimir($cuerpo);
}
?>