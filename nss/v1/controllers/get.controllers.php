<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use app\models\nss as NSSModel;

$app->get('/', function () use ($app) {

    return $app->json(array());
});

$app->get('/test', function () use ($app) {

    return $app->json(array());
});

$app->get('/extraer/ordenes', function () use ($app) {
    $u = new NSSModel\Ordenes;
    return $app->json($u->extraerOrdenes());
});

$app->get('/extraer/ordenes-diferido', function () use ($app) {
    $u = new NSSModel\Ordenes;
    return $app->json($u->extraerOrdenesDirefirido());
});

$app->get('/filtrar/ordenes', function () use ($app) {
    $u = new NSSModel\Filtrar;
    return $app->json($u->filtrarOrdenes());
});

$app->get('/filtrar-dia/ordenes', function () use ($app) {
    $u = new NSSModel\Filtrar;
    return $app->json($u->filtrarOrdenesDia());
});

$app->get('/validar-dia/ordenes', function () use ($app) {
    $u = new NSSModel\Validar;
    return $app->json($u->validarOrdenesDia());
});

$app->get('/validar/ordenes', function () use ($app) {
    $u = new NSSModel\Validar;
    return $app->json($u->validarOrdenes());
});

$app->get('/revalidar/ordenes', function () use ($app) {
    $u = new NSSModel\Enviar;
    return $app->json($u->revalidarOrdenes());
});

$app->get('/enviar/ordenes', function () use ($app) {
    $u = new NSSModel\Enviar;
    return $app->json($u->enviarOrdenes());
});

$app->get('/enviar/ordenes-pcr', function () use ($app) {
    $u = new NSSModel\Enviar;
    return $app->json($u->enviarOrdenesPCR());
});

// Filtrar
$app->get('/listar/ordenes', function () use ($app) {
    $u = new NSSModel\Listar;
    return $app->json($u->listarOrdenes());
});
