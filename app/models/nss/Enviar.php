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
use Doctrine\DBAL\DriverManager;
use Endroid\QrCode\QrCode;
use Ocrend\Kernel\Helpers as Helper;
use Ocrend\Kernel\Models\IModels;
use Ocrend\Kernel\Models\Models;
use Ocrend\Kernel\Models\ModelsException;
use Ocrend\Kernel\Router\IRouter;
use setasign\Fpdi\Fpdi;
use SoapClient;

/**
 * Modelo Enviar
 */
class Enviar extends Models implements IModels
{

    # Variables de clase
    private $ordenes = array();
    private $documento = array();
    private $viewPDF = 'https://pacientes.hospitalmetropolitano.org/resultado/l/';

    private function conectar_Oracle()
    {
        global $config;

        $_config = new \Doctrine\DBAL\Configuration();

        # SETEAR LA CONNEXION A LA BASE DE DATOS DE ORACLE GEMA
        $this->_conexion = \Doctrine\DBAL\DriverManager::getConnection($config['database']['drivers']['oracle'], $_config);
    }

    private function setSpanishOracle()
    {

        # 71001 71101
        $sql = "alter session set NLS_LANGUAGE = 'LATIN AMERICAN SPANISH'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = "alter session set NLS_TERRITORY = 'ECUADOR'";
        # Execute
        $stmt = $this->_conexion->query($sql);

        $sql = " alter session set NLS_DATE_FORMAT = 'DD/MM/YYYY' ";
        # Execute
        $stmt = $this->_conexion->query($sql);
    }

    public function enviarOrdenes(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/porenviar/');

            $i = 0;

            $timeControl = strtotime(date('Y-m-d H:i:s'));
            $primeraFranja = strtotime(date('Y-m-d 07:00:00'));
            $segundaFranja = strtotime(date('Y-m-d 14:00:00'));
            $terceraFranja = strtotime(date('Y-m-d 17:00:00'));
            $cuartaFranja = strtotime(date('Y-m-d 20:00:00'));
            $quintaFranja = strtotime(date('Y-m-d 22:00:00'));

            // EXTRAER  ORDENES PARA ENVIAR
            foreach ($list as $key => $val) {

                sleep(0.5);

                $content = file_get_contents($val);
                $documento = json_decode($content, true);
                $timeValDocumento = strtotime(date('Y-m-d H:i:s', strtotime($documento['fechaExamen'] . ' ' . $documento['horaExamen'])));

                if ($documento['_PCR'] == 0) {

                    if ($documento['enviosParciales'] == 1 && $documento['statusEnvio'] == 5) {

                        if ($i <= 11) {
                            $documento['file'] = $val;
                            $documento['statusEnvio'] = 0;
                            $documento['corteEnvio'] = 1;
                            $this->ordenes[] = $documento;
                            $i++;
                        }
                    } else {

                        if ($i <= 11) {

                            if ($timeControl > $primeraFranja && $timeValDocumento < $primeraFranja) {
                                # Primera Franja Resultados Validados Antes de las 7 AM
                                $documento['file'] = $val;
                                $documento['statusEnvio'] = 0;
                                $documento['corteEnvio'] = 1;
                                $this->ordenes[] = $documento;
                                $i++;
                            }

                            if ($timeControl > $segundaFranja && $timeValDocumento < $segundaFranja) {
                                # Segunda Franja Resultados Validados Antes de las 14 PM
                                $documento['file'] = $val;
                                $documento['statusEnvio'] = 0;
                                $documento['corteEnvio'] = 2;
                                $this->ordenes[] = $documento;
                                $i++;
                            }

                            if ($timeControl > $terceraFranja && $timeValDocumento < $terceraFranja) {
                                # Tercera Franja Resultados Validados Antes de las 17H00 PM
                                $documento['file'] = $val;
                                $documento['statusEnvio'] = 0;
                                $documento['corteEnvio'] = 3;
                                $this->ordenes[] = $documento;
                                $i++;
                            }

                            if ($timeControl > $cuartaFranja && $timeValDocumento < $cuartaFranja) {
                                # Tercera Franja Resultados Validados Antes de las 20H00 PM
                                $documento['file'] = $val;
                                $documento['statusEnvio'] = 0;
                                $documento['corteEnvio'] = 4;
                                $this->ordenes[] = $documento;
                                $i++;
                            }

                            if ($timeControl > $quintaFranja && $timeValDocumento < $quintaFranja) {
                                # Tercera Franja Resultados Validados Antes de las 22H00 PM
                                $documento['file'] = $val;
                                $documento['statusEnvio'] = 0;
                                $documento['corteEnvio'] = 5;
                                $this->ordenes[] = $documento;
                                $i++;
                            }
                        }
                    }
                }
            }

            // Enviar Ordenes de Envio
            $this->enviar();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,
            );
        } catch (ModelsException $e) {

            return array('status' => false, 'data' => $this->ordenes, 'message' => $this->ordenes, 'errorCode' => $e->getCode());
        }
    }

    public function revalidarOrdenes(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/porenviar/');

            $i = 0;

            return 1;

            foreach ($list as $k => $v) {

                $content = file_get_contents($v);
                $documento = json_decode($content, true);
                $documento['file'] = $v;
                @unlink($documento['file']);
                $documento['procesoValidacion'] = 1;
                $documento['validacionClinica'] = 0;
                $documento['validacionMicro'] = 0;
                $documento['tipoValidacion'] = 0;
                $documento['validacionesParciales'] = [];
                $file = 'ordenes/filtradas/sc_' . $documento['sc'] . '_' . $documento['fechaExamen'] . '.json';
                $json_string = json_encode($documento);
                file_put_contents($file, $json_string);
            }

            # Devolver Información
            return array(
                'status' => true,
                'data' => $list,
            );
        } catch (ModelsException $e) {

            return array('status' => false, 'data' => $this->ordenes, 'message' => $this->ordenes, 'errorCode' => $e->getCode());
        }
    }

    public function enviarOrdenesPCR(): array
    {

        try {

            global $config, $http;

            $list = Helper\Files::get_files_in_dir('../../nss/v1/ordenes/porenviar/');

            $i = 0;

            // Extraer ORDENES PARA VALIDAR
            foreach ($list as $key => $val) {

                if ($i <= 10) {
                    sleep(0.5);
                    $content = file_get_contents($val);
                    $documento = json_decode($content, true);

                    if ($documento['_PCR'] == 1) {
                        $documento['file'] = $val;
                        $documento['statusEnvio'] = 0;
                        $this->ordenes[] = $documento;
                        $i++;
                    }
                }
            }

            // Enviar Ordenes de Envio
            $this->enviar();

            # Devolver Información
            return array(
                'status' => true,
                'data' => $this->ordenes,
            );
        } catch (ModelsException $e) {

            return array('status' => false, 'data' => $this->ordenes, 'message' => $this->ordenes, 'errorCode' => $e->getCode());
        }
    }

    public function enviar()
    {

        try {

            $ordenes = array();

            foreach ($this->ordenes as $key) {

                $this->documento = $key;

                # Status Envio -1- Generacion de Corec de Paciente Correcta
                $this->generarCorreosElectronicosPaciente();

                # Status Envio -- Generar Fomrato QR Personalizado
                $this->enviarNotificacion();

                # ParA otros dias
                $this->verificarStatus();

                // Validar Resultdos
                if ($this->documento['statusEnvio'] == 4 && $this->documento['sc'] == $key['sc']) {

                    @unlink($this->documento['file']);
                    unset($this->documento['file']);

                    $this->documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'Envio exitoso de Notificación.',
                    );

                    $file = 'ordenes/enviadas/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
                    $json_string = json_encode($this->documento);
                    file_put_contents($file, $json_string);
                } else if ($this->documento['statusEnvio'] == 5 && $this->documento['sc'] == $key['sc']) {

                    @unlink($this->documento['file']);
                    unset($this->documento['file']);

                    $this->documento['procesoValidacion'] = 0;
                    $this->documento['validacionClinica'] = 0;
                    $this->documento['validacionMicro'] = 0;
                    $this->documento['tipoValidacion'] = 0;
                    $this->documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'Reproceso por Revalidación de resultado.',
                    );
                    $file = 'ordenes/filtradas/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
                    $json_string = json_encode($this->documento);
                    file_put_contents($file, $json_string);
                } else {

                    // Filtrada Para No Envío
                    @unlink($this->documento['file']);
                    unset($this->documento['file']);

                    $this->documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'Error en proceso de envío',
                    );

                    $file = 'ordenes/errores/enviadas/sc_' . $this->documento['sc'] . '_' . $this->documento['fechaExamen'] . '.json';
                    $json_string = json_encode($this->documento);
                    file_put_contents($file, $json_string);
                }

                $ordenes[] = $this->documento;
            }

            $this->ordenes = $ordenes;
        } catch (ModelsException $e) {

            throw new ModelsException('Error en proceso Enviae.');
        }
    }

    public function verificarStatus()
    {

        $estaValidada = true;

        $documento = $this->documento;

        foreach ($documento['dataClinica'] as $key => $val) {

            if ($val['TestStatus'] < 4) {
                $estaValidada = false;
            }
        }

        foreach ($documento['dataMicro'] as $key => $val) {

            if ($val['TestStatus'] < 4) {
                $estaValidada = false;
            }
        }

        if (count($documento['dataClinica']) == 0 && count($documento['dataMicro']) == 0) {
            $estaValidada = false;
        }

        if (!$estaValidada) {
            $documento['statusEnvio'] = 5;
        }

        $this->documento = $documento;
    }

    public function getMailNotificacion(string $correo = 'mchangcnt@gmail.com')
    {

        $documento = $this->documento;

        $idHashRes = Helper\Strings::ocrend_encode($documento['sc'] . '.' . date('d-m-Y', strtotime($documento['fechaExamen'])), 'hm');

        # Construir mensaje y enviar mensaje
        $content = 'Estimado(a).- <br /><br /><b>' . $documento['apellidosPaciente'] . ' ' . $documento['nombresPaciente'] . '</b> está disponible el link para consulta del resultado de Laboratorio.
                    <br />
                    <b>Fecha de Examen:</b> ' . $documento['fechaExamen'];

        # Enviar el correo electrónico
        $_html = Helper\Emails::loadTemplate(
            array(
                # Título del mensaje
                '{{title}}' => 'Servicio de Laboratorio - MetroVirtual',
                # Contenido del mensaje
                '{{content}}' => $content,
                # Url del botón
                '{{btn-href}}' => $this->viewPDF . $idHashRes,
                # Texto del boton
                '{{btn-name}}' => 'Ver Resultado de Laboratorio',
                # Copyright
                '{{copyright}}' => '&copy; ' . date('Y') . ' <a href="https://www.hospitalmetropolitano.org">MetroVirtual Hospital Metropolitano</a> Todos los derechos reservados.',
            ),
            11
        );

        # Verificar si hubo algún problema con el envió del correo
        $sendMail = $this->sendMailNotificacion($_html, $correo, 'Servicio de Laboratorio - MetroVirtual Hospital Metropolitano');

        return $sendMail;
    }

    public function sendMailNotificacion($html, $to, $subject)
    {

        global $config;

        ini_set('openssl.cafile', 'C:/Program Files/Git/mingw64/libexec/ssl/certs/ca-bundle.crt'); 


        if ($this->documento['_PCR'] == 1) {
            $email = $to;
        } else {
            $email = $to;
        }

        $stringData = array(
            "TextBody" => "Link Resultado de Laboratorio - MetroVirtual",
            'From' => 'MetroVirtual metrovirtual@hospitalmetropolitano.org',
            'To' => $email,
            'Bcc' => 'resultadoslab@hmetro.med.ec;martinfranciscochavez@gmail.com',
            'Subject' => $subject,
            'HtmlBody' => $html,
            'Tag' => 'NRLPV2',
            'TrackLinks' => 'HtmlAndText',
            'TrackOpens' => true,
        );

        $data = json_encode($stringData);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.trx.icommarketing.com/email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CERTINFO, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data),
                'X-Postmark-Server-Token: 7f14b454-8df3-4e75-9def-30e45cab59e9',
            )
        );

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $resultobj = curl_error($ch);
            return array('status' => false, 'data' => $resultobj);
        }

        curl_close($ch);
        $resultobj = json_decode($result);
        return array('status' => true, 'data' => $resultobj);
    }

    public function enviarCorreoPersonalizado($correoElectronico = '')
    {

        $envioExitoso = false;

        $logsEnvio = array();

        $statusEnvio = $this->getMailNotificacion($correoElectronico);

        if ($statusEnvio['status']) {

            $logsEnvio[] = $statusEnvio['data'];
        } else {

            $logsEnvio[] = $statusEnvio['data'];
        }

        if ($statusEnvio['status']) {
            $envioExitoso = true;
        }

        return array(
            'status' => $envioExitoso,
            'data' => $logsEnvio,
        );
    }

    public function enviarCorreoPaciente()
    {

        $envioExitoso = false;

        $documento = $this->documento;

        $logsEnvio = array();

        foreach ($documento['correosElectronicos'] as $key => $val) {

            $statusEnvio = $this->getMailNotificacion($val['dirrecciones']);

            if ($statusEnvio['status']) {

                $logsEnvio[] = $statusEnvio['data'];
            } else {

                $logsEnvio[] = $statusEnvio['data'];
            }

            if ($statusEnvio['status']) {
                $envioExitoso = true;
            }
        }

        return array(
            'status' => $envioExitoso,
            'data' => $logsEnvio,
        );
    }

    public function enviarNotificacion()
    {

        # Extraer Datos de Paciente
        $documento = $this->documento;

        $envioExitoso = false;

        $necesitaEnvio = false;

        $correosPersonalizados = array();

        foreach ($documento['reglasFiltrosEnvio'] as $key => $val) {

            $direcciones = json_decode($val['envio'], true);
            if ($direcciones['dirrecciones'] == '1') {
                $necesitaEnvio = true;
            } else {
                $stsEnvio = $this->enviarCorreoPersonalizado($direcciones['dirrecciones']);
                if ($stsEnvio['status']) {
                    $correosPersonalizados[] = $stsEnvio['data'];
                    $envioExitoso = true;
                }
            }
        }

        if (count($correosPersonalizados) !== 0) {
            $documento['logsEnvio'][] = array('correosPersonalizados' => $correosPersonalizados);
        }

        if ($necesitaEnvio) {

            $stsEnvio = $this->enviarCorreoPaciente();
            if ($stsEnvio['status']) {
                $documento['numEnvios'] = ($documento['numEnvios'] + 1);
                $documento['logsEnvio'][] = array('correosPaciente' => $stsEnvio['data']);
                $envioExitoso = true;
            } else {
                $documento['logsEnvio'][] = array(
                    'timestampLog' => date('Y-m-d H:i:s'),
                    'log' => $stsEnvio['data'],
                );
            }
        }

        if ($envioExitoso) {

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'Proceso de envío Exitoso',
            );
            $documento['statusEnvio'] = 4;
            $documento['fechaEnvio'] = date('Y-m-d H:i:s');
            $this->documento = $documento;
        } else {

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'Proceso de envío con Error ningún correo electrónico respondio afirmativamente.',
            );
            $this->documento = $documento;
        }
    }

    public function generarFormatoQR()
    {

        $documento = $this->documento;
        $documento['statusEnvio'] = 3;
        $this->documento = $documento;
    }

    public function generarQR_PCR($linkResultado)
    {

        try {

            $destination = "../../nss/v1/ordenes/downloads/" . $this->documento['sc'] . ".pdf";

            $fp = fopen($destination, 'w+');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $linkResultado);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);

            fclose($fp);

            $urlPdf = 'https://api.hospitalmetropolitano.org';

            # Generate QR CODE
            $qrCode = new QrCode($urlPdf);
            $qrCode->setLogoPath('../../nss/v1/ordenes/downloads/hm.png');

            // Save it to a file
            $qrCode->writeFile('../../nss/v1/ordenes/downloads/' . $this->documento['sc'] . '.png');

            $qrImage = '../../nss/v1/ordenes/downloads/' . $this->documento['sc'] . '.png';

            $qrAcess = '../../nss/v1/ordenes/downloads/acess.png';

            // Editar template PDF QR

            $pdf = new Fpdi();

            $staticIds = array();
            $pageCount = $pdf->setSourceFile($destination);
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $staticIds[$pageNumber] = $pdf->importPage($pageNumber);
            }

            // get the page count of the uploaded file
            $pageCount = $pdf->setSourceFile($destination);

            // let's track the page number for the filler page
            $fillerPageCount = 1;
            // import the uploaded document page by page
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {

                if ($fillerPageCount == 1) {
                    // add the current filler page
                    $pdf->AddPage();
                    $pdf->useTemplate($staticIds[$fillerPageCount]);
                    $pdf->Image($qrImage, 5, 237, 40, 40);
                    // QR ACESS
                    $pdf->Image($qrAcess, 46, 237.1, 40, 39);
                }

                // update the filler page number or reset it
                $fillerPageCount++;
                if ($fillerPageCount > count($staticIds)) {
                    $fillerPageCount = 1;
                }
            }

            $newDestination = "../../nss/v1/ordenes/downloads/" . $this->documento['sc'] . ".qr.pdf";

            $pdf->Output('F', $newDestination);

            $_file = base64_encode(file_get_contents($newDestination));

            @unlink($destination);
            @unlink($newDestination);
            @unlink($qrImage);

            return $_file;
        } catch (ModelsException $e) {

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'No fue posible generar el documento firmado QR.',
            );

            $this->documento = $documento;

            return false;
        }
    }

    public function generarResultadoPDF()
    {

        try {

            $documento = $this->documento;

            if ($documento['_PCR'] !== 0) {
                # INICIAR SESSION
                $this->wsLab_LOGIN_PCR();
            } else {
                # INICIAR SESSION
                $this->wsLab_LOGIN();
            }

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wso.ws.wReports.wsdl.xml');

            $Preview = $client->Preview(
                array(
                    "pstrSessionKey" => $this->pstrSessionKey,
                    "pstrSampleID" => $documento['sc'],
                    "pstrRegisterDate" => $documento['fechaExamen'],
                    "pstrFormatDescription" => 'METROPOLITANO',
                    "pstrPrintTarget" => 'Destino por defecto',
                )
            );

            # CERRAR SESSION POR LICENCIAS HSF
            $this->wsLab_LOGOUT();

            # No existe documento

            if (!isset($Preview->PreviewResult)) {

                $documento['logsEnvio'][] = array(
                    'timestampLog' => date('Y-m-d H:i:s'),
                    'log' => 'No existe el documento solicitado.',
                );

                $this->documento = $documento;
            }

            # No existe documento

            if (isset($Preview->PreviewResult) or $Preview->PreviewResult == '0') {

                if ($Preview->PreviewResult == '0') {

                    $documento['logsEnvio'][] = array(
                        'timestampLog' => date('Y-m-d H:i:s'),
                        'log' => 'No existe el documento solicitado.',
                    );

                    $this->documento = $documento;
                } else {

                    if ($documento['_PCR'] !== 0) {

                        $_file = $this->generarQR_PCR($Preview->PreviewResult);

                        if ($_file !== false) {

                            $documento['_PDF'] = array(
                                'Name' => 'resultado_' . $this->documento['sc'] . '.pdf',
                                'ContentType' => 'application/pdf',
                                'Content' => $_file,
                            );

                            $documento['statusEnvio'] = 2;
                            $this->documento = $documento;
                        } else {

                            $documento['logsEnvio'][] = array(
                                'timestampLog' => date('Y-m-d H:i:s'),
                                'log' => 'No existe el documento solicitado ERROR CONVERSION QR.',
                            );

                            $this->documento = $documento;
                        }
                    } else {

                        $_file = base64_encode(file_get_contents($Preview->PreviewResult));

                        $documento['_PDF'] = array(
                            'Name' => 'resultado_' . $documento['sc'] . '.pdf',
                            'ContentType' => 'application/pdf',
                            'Content' => $_file,
                        );

                        $documento['statusEnvio'] = 2;
                        $this->documento = $documento;
                    }
                }
            }
        } catch (SoapFault $e) {

            $this->wsLab_LOGOUT();

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'No existe el documento solicitado.',
            );

            $this->documento = $documento;
        } catch (ModelsException $b) {

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'No existe el documento solicitado.',
            );

            $this->documento = $documento;
        }
    }

    public function listarReglasEnvio()
    {

        # Extraer Datos de Paciente
        $documento = $this->documento;

        if (count($documento['reglasFiltrosEnvio']) !== 0) {

            foreach ($documento['reglasFiltrosEnvio'] as $key => $val) {

                #Para validaciones totales en CLINICA
                if ($val['tipoValidacion'] == 1 && $documento['tipoValidacion'] == 1 && $documento['validacionClinica'] == 1 && $val['strValidacion'] == 1) {

                    if (!is_null($val['envio'])) {
                        $direcciones = json_decode($val['envio'], true);
                        $documento['correosElectronicos'][] = $direcciones;
                    }
                }

                #Para validaciones totales en MICRO
                if ($val['tipoValidacion'] == 1 && $documento['tipoValidacion'] == 1 && $documento['validacionMicro'] == 1 && $val['strValidacion'] == 2) {

                    if (!is_null($val['envio'])) {
                        $direcciones = json_decode($val['envio'], true);
                        $documento['correosElectronicos'][] = $direcciones;
                    }
                }

                #Para validaciones parciales en Clinica
                if ($val['tipoValidacion'] == 0 && $documento['tipoValidacion'] == 0 && $documento['validacionClinica'] == 1 && $val['strValidacion'] == 1) {

                    if (!is_null($val['envio'])) {
                        $direcciones = json_decode($val['envio'], true);
                        $documento['correosElectronicos'][] = $direcciones;
                    }
                }

                #Para validaciones parciales en Micro
                if ($val['tipoValidacion'] == 0 && $documento['tipoValidacion'] == 0 && $documento['validacionMicro'] == 1 && $val['strValidacion'] == 2) {

                    if (!is_null($val['envio'])) {
                        $direcciones = json_decode($val['envio'], true);
                        $documento['correosElectronicos'][] = $direcciones;
                    }
                }
            }

            $this->documento = $documento;
        }
    }

    public function generarCorreosElectronicosPaciente()
    {

        # Extraer Datos de Paciente
        $documento = $this->documento;

        if (count($documento['correosElectronicos']) == 0) {

            $this->extraerDatosPaciente();
            $this->clearCorreosElectronicos();
        }
    }

    public function agregarCorreoEnvio($correo)
    {

        $existe = false;

        if (count($this->documento['correosElectronicos']) !== 0) {

            foreach ($this->documento['correosElectronicos'] as $key => $val) {

                if ($val['dirrecciones'] == $correo) {
                    $existe = true;
                }
            }
        }

        return $existe;
    }

    public function extraerDatosPaciente()
    {

        # Extraer Datos de Paciente
        $documento = $this->documento;

        $_sc = $documento['sc'];

        $sc = (int) $_sc;

        if ($sc > 22000000) {
            $nhc = $documento['numeroHistoriaClinica'] . '01';
        } else {
            $nhc = $documento['numeroHistoriaClinica'];
        }

        $sql = " SELECT
        b.fk_persona
        from cad_pacientes b
        where  b.pk_nhcl = '$nhc' ";

        # Conectar base de datos
        $this->conectar_Oracle();

        $this->setSpanishOracle();

        # Execute
        $stmt = $this->_conexion->query($sql);

        $this->_conexion->close();

        $data = $stmt->fetch();

        if ($data === false) {

            $documento['logsEnvio'][] = array(
                'timestampLog' => date('Y-m-d H:i:s'),
                'log' => 'NÚmero de Historia Clinica del Paciente no existe en BDD GEMA.',
            );

            $this->documento = $documento;
        } else {

            $codPersona = $data['FK_PERSONA'];

            $sql = "SELECT fun_busca_mail_persona(" . $codPersona . ") as emailsPaciente from dual ";

            # Conectar base de datos
            $this->conectar_Oracle();

            # Execute
            $stmt = $this->_conexion->query($sql);

            $this->_conexion->close();

            $data = $stmt->fetch();

            $getCorreos = $data['EMAILSPACIENTE'];

            if (is_null($getCorreos)) {

                $documento['logsEnvio'][] = array(
                    'timestampLog' => date('Y-m-d H:i:s'),
                    'log' => 'NÚmero de Historia Clinica del Paciente no devuelve ningún correo electrónico disponible en BDD GEMA.',
                );

                $this->documento = $documento;
            } else {

                $pos = strpos($getCorreos, '|');

                # Solo un correo
                if ($pos === false) {

                    $correoPaciente = $getCorreos;

                    $existeCorreo = $this->agregarCorreoEnvio($correoPaciente);

                    if (!$existeCorreo) {

                        $documento['correosElectronicos'][] = array(
                            'dirrecciones' => $correoPaciente,
                        );

                        $documento['statusEnvio'] = 1;

                        $this->documento = $documento;
                    }
                } else {

                    $_correosPacientes = explode('|', $getCorreos);

                    foreach ($_correosPacientes as $key => $val) {

                        $existeCorreo = $this->agregarCorreoEnvio($val);

                        if (!$existeCorreo) {

                            $documento['correosElectronicos'][] = array(
                                'dirrecciones' => $val,
                            );
                        }
                    }

                    $documento['statusEnvio'] = 1;

                    $this->documento = $documento;
                }
            }
        }
    }

    public function clearCorreosElectronicos()
    {

        if (count($this->documento['correosElectronicos']) !== 0) {
            foreach ($this->documento['correosElectronicos'] as $key => $val) {

                if ($val['dirrecciones'] == 1) {
                    unset($this->documento['correosElectronicos'][$key]);
                }
            }
        }
    }

    # Metodo LOGIN webservice laboratorio ROCHE
    public function wsLab_LOGIN()
    {

        try {

            $client = new SoapClient(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'zdk.ws.wSessions.wsdl.xml');

            $Login = $client->Login(
                array(
                    "pstrUserName" => "CONSULTA",
                    "pstrPassword" => "CONSULTA1",
                )
            );

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

            $Login = $client->Login(
                array(
                    "pstrUserName" => "CWMETRO",
                    "pstrPassword" => "CWM3TR0",
                )
            );

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

            $Logout = $client->Logout(
                array(
                    "pstrSessionKey" => $this->pstrSessionKey,
                )
            );

            return $Logout->LogoutResult;
        } catch (SoapFault $e) {
            return array('success' => false, 'message' => $e->getMessage());
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
