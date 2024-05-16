<?php

/*
 * This file is part of the Ocrend Framewok 3 package.
 *
 * (c) Ocrend Software <info@ocrend.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace app\models\nss;

use app\models\nss as Model;
use Exception;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use SoapClient;

/**
 * Modelo Validar
 */
class Validar extends Models implements IModels
{

    # Variables de clase
    private $ordenes = array();
    private $documento = array();

    public function validarOrdenes(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/filtradas/');

            $i = 0;

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($documento['fechaExamen'] !== date('Y-m-d')) {

                    if ($i <= 20 && $documento['procesoValidacion'] == 0) {

                        $documento['ultimaValidacion'] = date('Y-m-d H:i:s');
                        $this->ordenes[] = $documento;
                        $i++;

                    }

                }

            }

            // Filtrar Ordenes de Envio
            $this->validar();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,
                '_data' => $this->verificarStatusValidar(),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function validarOrdenesDia(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/filtradas/');

            $i = 0;

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($documento['fechaExamen'] == date('Y-m-d')) {

                    if ($i <= 20 && $documento['procesoValidacion'] == 0) {

                        $documento['ultimaValidacion'] = date('Y-m-d H:i:s');
                        $this->ordenes[] = $documento;
                        $i++;

                    }

                }

            }

            // Filtrar Ordenes de Envio
            $this->validar();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,
                '_data' => $this->verificarStatusValidarDia(),
            );

        } catch (ModelsException $e) {

            return array('status' => false, 'data' => [], 'message' => $e->getMessage(), 'errorCode' => $e->getCode());

        }

    }

    public function establecerValidacion()
    {

        try {

            $documento = $this->documento;

            foreach ($documento['reglasFiltrosEnvio'] as $key => $val) {

                if (is_null($val['xIdPrueba'])) {
                    $this->validarDataClinica();
                    $this->validarDataMicro();
                }

                if (!is_null($val['xIdPrueba']) && $documento['enviosParciales'] == 0) {
                    $this->documento['enviosParciales'] = ($this->documento['enviosParciales'] + 1);
                }

                # Validaciones personalizadas parciales

                if ($documento['numEnvios'] < $this->documento['enviosParciales']) {

                    # Se genera un envío siempre y cuando el test este pendiente pero toda la clinica validada
                    if ($val['statusEnvio'] == 0 && $val['tipoValidacion'] == 0 && ($val['strValidacion'] == 2 && !is_null($val['xIdPrueba']))) {
                        $this->validarDataClinicaParcial($val);

                    }

                    # Se genera un envío siempre y cuando el test este pendiente pero toda la micro validada
                    if ($val['statusEnvio'] == 0 && $val['tipoValidacion'] == 0 && ($val['strValidacion'] == 3 && !is_null($val['xIdPrueba']))) {
                        $this->validarDataMicroParcial($val);

                    }

                    # Se genera un envío siempre y cuando el test este pendiente pero toda la CLINICA validada
                    if ($val['statusEnvio'] == 0 && $val['tipoValidacion'] == 0 && ($val['strValidacion'] == 4 && !is_null($val['xIdPrueba']))) {
                        $this->validarDataClinicaParcial($val);

                    }

                    # Se genera un envío siempre y cuando el test este pendiente pero toda la micro validada
                    if ($val['statusEnvio'] == 0 && $val['tipoValidacion'] == 0 && ($val['strValidacion'] == 5 && !is_null($val['xIdPrueba']))) {
                        $this->validarDataMicroParcial($val);

                    }

                    # Se genera un envío siempre y cuando el test este validado y toda la clinica validada
                    if ($val['statusEnvio'] == 0 && $val['tipoValidacion'] == 1 && ($val['strValidacion'] == 2 && !is_null($val['xIdPrueba']))) {
                        $this->validarDataClinicaParcial($val);
                    }

                    # Se genera un envío siempre y cuando el test este validado y toda la micro validada
                    if ($val['statusEnvio'] == 0 && $val['tipoValidacion'] == 1 && ($val['strValidacion'] == 3 && !is_null($val['xIdPrueba']))) {
                        $this->validarDataMicroParcial($val);
                    }

                    # Se genera un envío siempre y cuando el test este validado y toda la clinica validada
                    if ($val['statusEnvio'] == 0 && $val['tipoValidacion'] == 1 && ($val['strValidacion'] == 4 && !is_null($val['xIdPrueba']))) {
                        $this->validarDataClinicaParcial($val);
                    }

                    # Se genera un envío siempre y cuando el test este validado y toda la micro validada
                    if ($val['statusEnvio'] == 0 && $val['tipoValidacion'] == 1 && ($val['strValidacion'] == 5 && !is_null($val['xIdPrueba']))) {
                        $this->validarDataMicroParcial($val);
                    }

                }

            }

        } catch (ModelsException $e) {

            throw new ModelsException('Error en proceso Validar.');

        }

    }

    public function verificarStatus($documento)
    {

        $estaValidadaClinica = true;

        $estaValidadaMicro = true;

        if (count($documento['dataClinica']) == 0) {
            $estaValidadaClinica = false;

        }
        if (count($documento['dataMicro']) == 0) {
            $estaValidadaMicro = false;

        }

        if (!$estaValidadaClinica && !$estaValidadaMicro) {
            return false;
        }

        return true;

    }

    public function validar()
    {

        try {

            $ordenes = array();

            // Extraer ORDENES PARA FILTRAR
            foreach ($this->ordenes as $key) {

                $this->documento = $key;

                @unlink($this->documento['file']);
                unset($this->documento['file']);
                $this->documento['procesoValidacion'] = 1;
                $file = 'ordenes/porenviar/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
                $json_string = json_encode($this->documento);
                file_put_contents($file, $json_string);

                $ordenes[] = $this->documento;

            }

            $this->ordenes = $ordenes;

        } catch (ModelsException $e) {

            throw new ModelsException('Error en proceso Validar.');

        }

    }

    public function verificarStatusValidar()
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/filtradas/');

            $i = 0;

            $cicloCorrido = true;
            $ordenes = array();

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($documento['fechaExamen'] !== date('Y-m-d')) {

                    if ($documento['procesoValidacion'] == 0) {
                        $cicloCorrido = false;
                    }

                    $ordenes[] = $documento;

                }

            }

            if ($cicloCorrido) {

                foreach ($ordenes as $k => $v) {

                    $content = file_get_contents($v['file']);
                    $documento = json_decode($content, true);
                    @unlink($documento['file']);
                    $documento['procesoValidacion'] = 0;
                    $file = 'ordenes/filtradas/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json';
                    $json_string = json_encode($documento);
                    file_put_contents($file, $json_string);

                }

            }

            return $ordenes;

        } catch (ModelsException $e) {

            throw new ModelsException('No se puede continuar error en Generar Reporoceso de Validación.');

        }

    }

    public function verificarStatusValidarDia()
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/filtradas/');

            $i = 0;

            $cicloCorrido = true;
            $ordenes = array();

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $documento['file'] = $val;

                if ($documento['fechaExamen'] == date('Y-m-d')) {

                    if ($documento['procesoValidacion'] == 0) {
                        $cicloCorrido = false;
                    }

                    $ordenes[] = $documento;

                }

            }

            if ($cicloCorrido) {

                foreach ($ordenes as $k => $v) {

                    $content = file_get_contents($v['file']);
                    $documento = json_decode($content, true);
                    @unlink($documento['file']);
                    $documento['procesoValidacion'] = 0;
                    $file = 'ordenes/filtradas/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json';
                    $json_string = json_encode($documento);
                    file_put_contents($file, $json_string);

                }

            }

            return $ordenes;

        } catch (ModelsException $e) {

            throw new ModelsException('No se puede continuar error en Generar Reporoceso de Validación.');

        }

    }

    # Metodo LOGIN webservice laboratorio ROCHE
    public function wsLab_LOGIN()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(array(
                "pstrUserName" => "CONSULTA",
                "pstrPassword" => "CONSULTA1",
            ));

            # Guaradar  KEY de session WS
            $this->pstrSessionKey = $Login->LoginResult;

            # Retorna KEY de session WS
            return $Login->LoginResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    public function wsLab_LOGIN_PCR()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(array(
                "pstrUserName" => "CWMETRO",
                "pstrPassword" => "CWM3TR0",
            ));

            # Guaradar  KEY de session WS
            $this->pstrSessionKey = $Login->LoginResult;

            # Retorna KEY de session WS
            # return $Login->LoginResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    # Metodo LOGOUT webservice laboratorio ROCHE
    public function wsLab_LOGOUT()
    {

        try {

            # INICIAR SESSION
            # $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Logout = $client->Logout(array(
                "pstrSessionKey" => $this->pstrSessionKey,
            ));

            return $Logout->LogoutResult;

        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    public function extraerDataPCR($documento)
    {

        try {

            # INICIAR SESSION

            $this->wsLab_LOGIN_PCR();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $this->documento['sc'],
                'pstrRegisterDate' => $this->documento['fechaExamen'],
            ));

            $this->wsLab_LOGOUT();

            if (!isset($Preview->GetResultsResult)) {
                throw new ModelsException('No existe información.');
            }

            $listaPruebas = $Preview->GetResultsResult->Orders->LISOrder->LabTests->LISLabTest;

            $i = 0;

            $lista = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $lista[] = array(
                        'TestID' => $key->TestID,
                        'TestStatus' => $key->TestStatus,
                        'TestName' => $key->TestName,
                        'ultimaValidacion' => '',
                    );
                }
            } else {
                $lista[] = array(
                    'TestID' => $listaPruebas->TestID,
                    'TestStatus' => $listaPruebas->TestStatus,
                    'TestName' => $listaPruebas->TestName,
                    'ultimaValidacion' => '',
                );
            }

            return $lista;

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            $this->documento['dataClinica'] = array(5);

        } catch (ModelsException $b) {

            $this->documento['dataClinica'] = array(6);

        }
    }

    public function pruebaValidada($xIdPrueba)
    {

        try {

            # INICIAR SESSION

            sleep(1.5);

            $pruebaValidada = false;

            if ($this->documento['_PCR'] == 1) {
                $this->wsLab_LOGIN_PCR();
            } else {
                $this->wsLab_LOGIN();
            }

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wOrderTests.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetStatus(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $this->documento['sc'],
                'pstrRegisterDate' => $this->documento['fechaExamen'],
                'pintTestID' => $xIdPrueba,
            ));

            $this->wsLab_LOGOUT();

            if (!isset($Preview->GetStatusResult)) {
                throw new ModelsException('No existe información.');
            }

            $statusPrueba = $Preview->GetStatusResult;

            if ($statusPrueba >= 4) {
                $pruebaValidada = true;
            }

            return $pruebaValidada;

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            return $pruebaValidada;

        } catch (ModelsException $b) {

            return $pruebaValidada;

        }
    }

    public function verificarStatusPruebasClinicas($regla)
    {

        try {

            # INICIAR SESSION

            sleep(1.5);

            $pruebaValidada = true;

            # 1001 -> hematologia
            # 2001 -> quimica
            # 2701 -> gasometria lectrolitos
            # 1250 -> cuagulacion
            # 1254 -> cuagulacion

            $pruebasConfiguracion = array('1001', '2001', '2701', '1250', '1254');

            $pruebasClinica = array();

            foreach ($this->documento['orderTests'] as $k) {
                if (in_array($k['TestID'], $pruebasConfiguracion)) {
                    $pruebasClinica[] = $k['TestID'];
                }
            }

            foreach ($pruebasClinica as $key) {

                if ($key !== $regla['xIdPrueba']) {

                    sleep(1);

                    if ($this->documento['_PCR'] == 1) {
                        $this->wsLab_LOGIN_PCR();
                    } else {
                        $this->wsLab_LOGIN();
                    }

                    $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wOrderTests.xml', array('soap_version' => SOAP_1_1,
                        'exceptions' => true,
                        'trace' => 1,
                        'cache_wsdl' => WSDL_CACHE_NONE,
                    ));

                    $Preview = $client->GetStatus(array(
                        'pstrSessionKey' => $this->pstrSessionKey,
                        'pstrSampleID' => $this->documento['sc'],
                        'pstrRegisterDate' => $this->documento['fechaExamen'],
                        'pintTestID' => $key,
                    ));

                    $this->wsLab_LOGOUT();

                    if (!isset($Preview->GetStatusResult)) {
                        $pruebaValidada = false;
                    }

                    $statusPrueba = $Preview->GetStatusResult;

                    if ($statusPrueba < 4) {
                        $pruebaValidada = false;
                    }
                }

            }

            return $pruebaValidada;

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            return $pruebaValidada;

        } catch (ModelsException $b) {

            return $pruebaValidada;

        }
    }

    public function verificarStatusPruebasMicro($regla)
    {

        try {

            # INICIAR SESSION

            $pruebaValidada = false;

            foreach ($this->documento['dataMicro'] as $k) {
                if ($k['TestStatus'] >= 4) {
                    $pruebaValidada = true;
                }
            }

            return $pruebaValidada;

        } catch (ModelsException $b) {

            return $pruebaValidada;

        }
    }

    public function validarDataClinicaParcial($regla)
    {

        try {

            # INICIAR SESSION

            sleep(1.5);

            $pruebaValidada = false;

            if ($this->documento['_PCR'] == 1) {
                $this->wsLab_LOGIN_PCR();
            } else {
                $this->wsLab_LOGIN();
            }

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wOrderTests.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetStatus(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $this->documento['sc'],
                'pstrRegisterDate' => $this->documento['fechaExamen'],
                'pintTestID' => $regla['xIdPrueba'],
            ));

            $this->wsLab_LOGOUT();

            if (!isset($Preview->GetStatusResult)) {
                throw new ModelsException('No existe información.');
            }

            $statusPrueba = $Preview->GetStatusResult;

            # Para Validaciones cobinadas

            # Es prueba d emicro pero se libera si todo el resultado de clinca esta listo
            if ($regla['tipoValidacion'] == 1 && $regla['strValidacion'] == 2 && $this->documento['validacionClinica'] == 0) {
                if ($statusPrueba >= 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 1 && $regla['strValidacion'] == 3 && $this->documento['validacionMicro'] == 0) {
                # Es prueba d emicro pero se libera si todo el resultado de micro esta listo
                if ($statusPrueba >= 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 1 && $regla['strValidacion'] == 4 && $this->documento['validacionClinica'] == 1) {
                if ($statusPrueba >= 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 1 && $regla['strValidacion'] == 5 && $this->documento['validacionMicro'] == 1) {
                # Es prueba d emicro pero se libera si todo el resultado de micro esta listo
                if ($statusPrueba >= 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 0 && $regla['strValidacion'] == 2 && $this->documento['validacionClinica'] == 0) {
                # sE LIBERA SI ESTA PENDIENTE PERO UNA DE LA SPREUBAS DE CLINICA ESTA VALIDADA
                if ($statusPrueba < 4 && $this->verificarStatusPruebasClinicas($regla) !== false) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 0 && $regla['strValidacion'] == 3 && $this->documento['validacionMicro'] == 0) {
                # sE LIBERA SI ESTA PENDIENTE PERO POR LO MENOS UNA DE MICRO ESTA VALIDADA
                if ($statusPrueba < 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 0 && $regla['strValidacion'] == 4 && $this->documento['validacionClinica'] == 1) {
                # sE LIBERA SI ESTA PENDIENTE PERO TODO LA LINICA VALIDADA
                if ($statusPrueba < 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 0 && $regla['strValidacion'] == 5 && $this->documento['validacionMicro'] == 1) {
                # sE LIBERA SI ESTA PENDIENTE PERO TODO LA MICRO  VALIDADA
                if ($statusPrueba < 4) {
                    $pruebaValidada = true;
                }
            }

            if ($pruebaValidada) {

                if (!in_array($regla['xIdPrueba'], $this->documento['validacionesParciales'])) {
                    $this->documento['validacionesParciales'][] = $regla['xIdPrueba'];
                }

                $this->documento['validacionClinica'] = 1;
                $this->documento['tipoValidacion'] = 0;

            }

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            $this->documento['_AC'] = -1;

        } catch (ModelsException $b) {

            $this->documento['_AC'] = -2;

        }
    }

    public function validarDataClinica()
    {

        try {

            # INICIAR SESSION

            sleep(1.5);

            $pruebaValidada = true;

            if ($this->documento['_PCR'] == 1) {
                $this->wsLab_LOGIN_PCR();
            } else {
                $this->wsLab_LOGIN();
            }

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $this->documento['sc'],
                'pstrRegisterDate' => $this->documento['fechaExamen'],
            ));

            $this->wsLab_LOGOUT();

            if (!isset($Preview->GetResultsResult)) {
                throw new ModelsException('No existe información.');
            }

            $listaPruebas = $Preview->GetResultsResult->Orders->LISOrder->LabTests->LISLabTest;

            $i = 0;

            $lista = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $lista[] = array(
                        'TestID' => $key->TestID,
                        'TestStatus' => $key->TestStatus,
                        'TestName' => $key->TestName,
                        'ultimaValidacion' => date('Y-m-d H:i:s'),
                    );
                }
            } else {
                $lista[] = array(
                    'TestID' => $listaPruebas->TestID,
                    'TestStatus' => $listaPruebas->TestStatus,
                    'TestName' => $listaPruebas->TestName,
                    'ultimaValidacion' => date('Y-m-d H:i:s'),
                );
            }

            $this->documento['dataClinica'] = $lista;

            $this->documento['_AC'] = 11;

            foreach ($lista as $k) {

                if ($k['TestStatus'] < 4) {
                    $pruebaValidada = false;
                }

            }

            if ($pruebaValidada) {
                $this->documento['validacionClinica'] = 1;
                $this->documento['tipoValidacion'] = 1;
            }

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            $this->documento['validacionClinica'] = 0;

        } catch (ModelsException $b) {

            $this->documento['validacionClinica'] = 0;

        }
    }

    public function validarDataMicroParcial($regla)
    {

        try {

            # INICIAR SESSION

            sleep(1.5);

            $pruebaValidada = false;

            if ($this->documento['_PCR'] == 1) {
                $this->wsLab_LOGIN_PCR();
            } else {
                $this->wsLab_LOGIN();
            }

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wOrderTests.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetStatus(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $this->documento['sc'],
                'pstrRegisterDate' => $this->documento['fechaExamen'],
                'pintTestID' => $regla['xIdPrueba'],
            ));

            $this->wsLab_LOGOUT();

            if (!isset($Preview->GetStatusResult)) {
                throw new ModelsException('No existe información.');
            }

            $statusPrueba = $Preview->GetStatusResult;

            # Para Validaciones cobinadas

            # Es prueba d emicro pero se libera si todo el resultado de clinca esta listo
            if ($regla['tipoValidacion'] == 1 && $regla['strValidacion'] == 2 && $this->documento['validacionClinica'] == 0) {
                if ($statusPrueba >= 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 1 && $regla['strValidacion'] == 3 && $this->documento['validacionMicro'] == 0) {
                # Es prueba d emicro pero se libera si todo el resultado de micro esta listo
                if ($statusPrueba >= 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 1 && $regla['strValidacion'] == 4 && $this->documento['validacionClinica'] == 1) {
                if ($statusPrueba >= 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 1 && $regla['strValidacion'] == 5 && $this->documento['validacionMicro'] == 1) {
                # Es prueba d emicro pero se libera si todo el resultado de micro esta listo
                if ($statusPrueba >= 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 0 && $regla['strValidacion'] == 2 && $this->documento['validacionClinica'] == 0) {
                # sE LIBERA SI ESTA PENDIENTE PERO TODO LA LINICA VALIDADA
                if ($statusPrueba < 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 0 && $regla['strValidacion'] == 3 && $this->documento['validacionMicro'] == 0) {
                # SE LIBERA SI ESTA PENDIENTE PERO POR LO MENOS UNA PRUEBA DE MICRO ESTA PENDIENTE
                if ($statusPrueba < 4 && $this->verificarStatusPruebasMicro($regla) !== false) {
                    $pruebaValidada = true;

                }
            }

            if ($regla['tipoValidacion'] == 0 && $regla['strValidacion'] == 4 && $this->documento['validacionClinica'] == 1) {
                # sE LIBERA SI ESTA PENDIENTE PERO TODO LA LINICA VALIDADA
                if ($statusPrueba < 4) {
                    $pruebaValidada = true;
                }
            }

            if ($regla['tipoValidacion'] == 0 && $regla['strValidacion'] == 5 && $this->documento['validacionMicro'] == 1) {
                # sE LIBERA SI ESTA PENDIENTE PERO TODO LA MICRO  VALIDADA
                if ($statusPrueba < 4) {
                    $pruebaValidada = true;
                }
            }

            if ($pruebaValidada) {

                if (!in_array($regla['xIdPrueba'], $this->documento['validacionesParciales'])) {
                    $this->documento['validacionesParciales'][] = $regla['xIdPrueba'];
                }

                $this->documento['validacionMicro'] = 1;
                $this->documento['tipoValidacion'] = 0;

            }

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

        } catch (ModelsException $b) {

        }
    }

    public function validarDataMicro()
    {

        try {

            # INICIAR SESSION

            sleep(1.5);

            $pruebaValidada = true;

            $this->wsLab_LOGIN();

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wResults.xml', array('soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ));

            $Preview = $client->GetMicroResults(array(
                'pstrSessionKey' => $this->pstrSessionKey,
                'pstrSampleID' => $this->documento['sc'],
                'pstrRegisterDate' => $this->documento['fechaExamen'],
            ));

            $this->wsLab_LOGOUT();

            if (!isset($Preview->GetMicroResultsResult)) {
                throw new ModelsException('No existe información.');
            }

            # REVISAR SI EXISTEN PRUEBAS NO DISPONIBLES
            $listaPruebas = $Preview->GetMicroResultsResult->Orders->LISOrder->MicSpecs->LISMicSpec;

            $i = 0;

            $cultivos = array();

            if (is_array($listaPruebas)) {
                foreach ($listaPruebas as $key) {
                    $cultivos[] = array(
                        'SpecimenName' => $key->SpecimenName,
                        'Tests' => $key->MicTests->LISLabTest,
                    );
                }
            } else {
                $cultivos[] = array(
                    'SpecimenName' => $listaPruebas->SpecimenName,
                    'Tests' => $listaPruebas->MicTests->LISLabTest,

                );
            }

            $lista = array();

            foreach ($cultivos as $k) {

                if (is_array($k['Tests'])) {
                    foreach ($k['Tests'] as $b) {
                        $lista[] = array(
                            'TestID' => $b->TestID,
                            'TestStatus' => $b->TestStatus,
                            'TestName' => $b->TestName,
                            'ultimaValidacion' => date('Y-m-d H:i:s'),

                        );
                    }
                } else {
                    $lista[] = array(
                        'TestID' => $k['Tests']->TestID,
                        'TestStatus' => $k['Tests']->TestStatus,
                        'TestName' => $k['Tests']->TestName,
                        'ultimaValidacion' => date('Y-m-d H:i:s'),

                    );
                }

            }

            $this->documento['dataMicro'] = $lista;

            foreach ($lista as $k) {

                if ($k['TestStatus'] < 4) {
                    $pruebaValidada = false;
                }

            }

            if ($pruebaValidada) {
                $this->documento['validacionMicro'] = 1;
                $this->documento['tipoValidacion'] = 1;
            }

        } catch (\Exception $e) {

            $this->wsLab_LOGOUT();

            $this->documento['validacionMicro'] = 0;

        } catch (ModelsException $b) {

            $this->documento['validacionMicro'] = 0;

        }
    }

    private function quitar_tildes($cadena)
    {
        $no_permitidas = array("%", "é", "í", "ó", "ú", "É", "Í", "Ó", "Ú", "ñ", "À", "Ã", "Ì", "Ò", "Ù", "Ã™", "Ã ", "Ã¨", "Ã¬", "Ã²", "Ã¹", "ç", "Ç", "Ã¢", "ê", "Ã®", "Ã´", "Ã»", "Ã‚", "ÃŠ", "ÃŽ", "Ã”", "Ã›", "ü", "Ã¶", "Ã–", "Ã¯", "Ã¤", "«", "Ò", "Ã", "Ã„", "Ã‹");
        $permitidas = array("", "e", "i", "o", "u", "E", "I", "O", "U", "n", "N", "A", "E", "I", "O", "U", "a", "e", "i", "o", "u", "c", "C", "a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "u", "o", "O", "i", "a", "e", "U", "I", "A", "E");
        $texto = str_replace($no_permitidas, $permitidas, $cadena);
        return $texto;
    }

    private function sanear_string($string)
    {

        $string = trim($string);

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(
            array(">", "< ", ";", ",", ":", "%", "|", "-", "/"),
            ' ',
            $string
        );

        return trim($string);
    }

    # Ordenar array por campo
    public function orderMultiDimensionalArray($toOrderArray, $field, $inverse = 'desc')
    {
        $position = array();
        $newRow = array();
        foreach ($toOrderArray as $key => $row) {
            $position[$key] = $row[$field];
            $newRow[$key] = $row;
        }
        if ($inverse == 'desc') {
            arsort($position);
        } else {
            asort($position);
        }
        $returnArray = array();
        foreach ($position as $key => $pos) {
            $returnArray[] = $newRow[$key];
        }
        return $returnArray;
    }

    private function get_Order_Pagination(array $arr_input)
    {
        # SI ES DESCENDENTE

        $arr = array();
        $NUM = 1;

        if ($this->sortType == 'desc') {

            $NUM = count($arr_input);
            foreach ($arr_input as $key) {
                $key['NUM'] = $NUM;
                $arr[] = $key;
                $NUM--;
            }

            return $arr;

        }

        # SI ES ASCENDENTE

        foreach ($arr_input as $key) {
            $key['NUM'] = $NUM;
            $arr[] = $key;
            $NUM++;
        }

        return $arr; // 1000302446
    }

    private function get_page(array $input, $pageNum, $perPage)
    {
        $start = ($pageNum - 1) * $perPage;
        $end = $start + $perPage;
        $count = count($input);

        // Conditionally return results
        if ($start < 0 || $count <= $start) {
            // Page is out of range
            return array();
        } else if ($count <= $end) {
            // Partially-filled page
            return array_slice($input, $start);
        } else {
            // Full page
            return array_slice($input, $start, $end - $start);
        }
    }

    private function notResults(array $data)
    {
        if (count($data) == 0) {
            throw new ModelsException('No existe más resultados.', 4080);
        }
    }

/**
 * __construct()
 */

    public function __construct(IRouter $router = null)
    {
        parent::__construct($router);

    }
}
