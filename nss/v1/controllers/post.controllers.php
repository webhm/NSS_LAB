<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use app\models\touch as TModel;

/**
 * Auntenticar api del hospital modelo LOGIN function auth_Api
 *
 * @return json
 */

$app->post('/buscar-paciente', function () use ($app) {
    $u = new TModel\Pacientes;
    return $app->json($u->buscarPaciente());
});

$app->post('/save/log/formulario', function () use ($app) {
    $u = new TModel\Formularios;
    return $app->json($u->nuevoRegistro());
});

$app->post('/send-pedido-lab/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->registroPedido($idPedido));
});

$app->post('/send-pedido-eme-lab/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->registroPedidoEme($idPedido));
});

$app->post('/up-pedido-eme-lab/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->actualizarPedidoEme($idPedido));
});

$app->post('/up-pedido-lab/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->actualizarPedido($idPedido));
});

$app->post('/noti-eme/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->notiPedidoEme($idPedido));
});

$app->post('/message-pedido/{idPedido}', function ($idPedido) use ($app) {
    $u = new TModel\Notificaciones;
    return $app->json($u->messagePedido($idPedido));
});

$app->post('/auth', function () use ($app) {
    $u = new TModel\Users;
    return $app->json($u->login());
});

# Metrovirtual para Medicos

$app->post('/status-paciente-emergencia', function () use ($app) {
    $u = new TModel\Emergencia;
    return $app->json($u->getStatusPaciente_Emergencia());
});

$app->post('/sv-paciente-emergencia', function () use ($app) {
    $u = new TModel\Emergencia;
    return $app->json($u->getSVPaciente_Emergencia());
});

$app->post('/ev-paciente-emergencia', function () use ($app) {
    $u = new TModel\Emergencia;
    return $app->json($u->getFormularios_MV_005());
});

$app->post('/pacientes-admisiones', function () use ($app) {
    $m = new TModel\Admisiones;
    return $app->json($m->buscarPaciente());
});

$app->post('/integracion-higienizacion', function () use ($app) {
    $m = new TModel\Admisiones;
    return $app->json($m->buscarPaciente());
});

$app->post('/status-pedido-lab', function () use ($app) {
    global $config, $http;

    $m = new TModel\Laboratorio;

    return $app->json($m->getStatusPedidoLabDetalle($http->request->get('numeroPedido')));
});

$app->post('/up-status-pedido-lab', function () use ($app) {
    global $config, $http;

    $m = new TModel\Laboratorio;
    return $app->json($m->updateTomaMuestraPedido());
});

$app->post('/status-receta', function () use ($app) {
    global $config, $http;

    $m = new TModel\Farmacia;

    return $app->json($m->getStatusRecetaDetalle($http->request->get('numeroReceta')));
});
