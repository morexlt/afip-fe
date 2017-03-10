<?php
/*
Ejemplo en codigo php para conectarse y obtener cae mediante uso de web service Afip en modo homologacion (testing).
Este programa se entrega ABSOLUTAMENTE SIN GARANTIA.
El siguiente codigo fuente es una adaptacion de ejemplos encontrados en la web.
2015 Pablo <pablin.php@gmail.com>
*/
class WSFEV1 {
	const CUIT 	= 12345678912;                 		# CUIT del emisor de las facturas. Solo numeros sin comillas.

	const TA 	= "xml/TA.xml";        				# Archivo con el Token y Sign
	//https://wswhomo.afip.gov.ar/wsfev1/service.asmx // Funciones
	//https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL // para obtener WSDL
	const WSDL = "wsfev1.wsdl";                   	# The WSDL corresponding to WSFEV1	
	const LOG_XMLS = true;                     		# For debugging purposes
	const WSFEURL = "https://wswhomo.afip.gov.ar/wsfev1/service.asmx"; // homologacion wsfev1 (testing)
	//const WSFEURL = "?????????/wsfev1/service.asmx"; // produccion  

	/*
	* path real del directorio principal terminado en /
	*/
	//private $path = '/www/afipfev1/'; //caso linux
	private $path = 'c:/www/afipfev1/'; //caso windows (no importa que las barras esten como en linux)
	
	/*
	* manejo de errores
	*/
	public $error = '';
	public $ObsCode = '';
	public $ObsMsg = '';
	public $Code = '';
	public $Msg = '';
	/**
	* Cliente SOAP
	*/
	private $client;
  
	/*
	* objeto que va a contener el xml de TA
	*/
	private $TA;
  
	/*
	* Constructor
	*/
	public function __construct()
	{
    
    // seteos en php
    ini_set("soap.wsdl_cache_enabled", "0");    
    
    // validar archivos necesarios
    if (!file_exists($this->path.self::WSDL)) $this->error .= " Failed to open ".self::WSDL;
    
    if(!empty($this->error)) {
		throw new Exception('WSFE class. Faltan archivos necesarios para el funcionamiento');
    }        
	
    $this->client = new SoapClient($this->path.self::WSDL, array( 
				'soap_version' => SOAP_1_2,
				'location'     => self::WSFEURL,
				'exceptions'   => 0,
				'trace'        => 1)
    ); 
	}
  
	/*
	* Chequea los errores en la operacion, si encuentra algun error falta lanza una exepcion
	* si encuentra un error no fatal, loguea lo que paso en $this->error
	*/
	private function _checkErrors($results, $method)
	{
    if (self::LOG_XMLS) {
		file_put_contents("xml/request-".$method.".xml",$this->client->__getLastRequest());
		file_put_contents("xml/response-".$method.".xml",$this->client->__getLastResponse());
    }
    
    if (is_soap_fault($results)) {
		throw new Exception('WSFE class. FaultString: ' . $results->faultcode.' '.$results->faultstring);
    }
    
    if ($method == 'FEDummy') {return;}
    
    $XXX=$method.'Result';
	if ($results->$XXX->Errors->Err->Code != 0) {
		$this->error = "Method=$method errcode=".$results->$XXX->Errors->Err->Code." errmsg=".$results->$XXX->Errors->Err->Msg;
    }
    	
	
	//asigna error a variable
	if ($method == 'FECAESolicitar') {
		if ($results->$XXX->FeDetResp->FECAEDetResponse->Observaciones->Obs->Code){	
			$this->ObsCode = $results->$XXX->FeDetResp->FECAEDetResponse->Observaciones->Obs->Code;
			$this->ObsMsg = $results->$XXX->FeDetResp->FECAEDetResponse->Observaciones->Obs->Msg;
		}
		
		//if ($results->$XXX->FeDetResp->FECAEDetResponse->Observaciones->Obs[0]->Code){	
		//	$this->ObsCode = $results->$XXX->FeDetResp->FECAEDetResponse->Observaciones->Obs[0]->Code;
		//	$this->ObsMsg = $results->$XXX->FeDetResp->FECAEDetResponse->Observaciones->Obs[0]->Msg;
		//}		
	}
	$this->Code = $results->$XXX->Errors->Err->Code;
    $this->Msg = $results->$XXX->Errors->Err->Msg;	
	//fin asigna error a variable
		
	return $results->$XXX->Errors->Err->Code != 0 ? true : false;
	}

	/**
	* Abre el archivo de TA xml,
	* si hay algun problema devuelve false
	*/
	public function openTA()
	{
	$this->TA = simplexml_load_file($this->path.self::TA);

	return $this->TA == false ? false : true;
	}
  
	/*
	* Retorna el ultimo número autorizado.
	*/ 
	public function FECompUltimoAutorizado($ptovta, $tipo_cbte)
	{
	$results = $this->client->FECompUltimoAutorizado(
		array('Auth'=>array('Token' => $this->TA->credentials->token,
							'Sign' => $this->TA->credentials->sign,
							'Cuit' => self::CUIT),
			'PtoVta' => $ptovta,
			'CbteTipo' => $tipo_cbte));
			
    $e = $this->_checkErrors($results, 'FECompUltimoAutorizado');
	
    return $e == false ? $results->FECompUltimoAutorizadoResult->CbteNro : false;
	} //end function FECompUltimoAutorizado
  
	/*
	* Retorna el ultimo comprobante autorizado para el tipo de comprobante /cuit / punto de venta ingresado.
	*/ 
	public function recuperaLastCMP ($ptovta, $tipo_cbte)
	{
	$results = $this->client->FERecuperaLastCMPRequest(
		array('argAuth' =>  array('Token' => $this->TA->credentials->token,
								'Sign' => $this->TA->credentials->sign,
								'cuit' => self::CUIT),
			'argTCMP' => array('PtoVta' => $ptovta,
								'TipoCbte' => $tipo_cbte)));
	$e = $this->_checkErrors($results, 'FERecuperaLastCMPRequest');
	
	return $e == false ? $results->FERecuperaLastCMPRequestResult->cbte_nro : false;
	} //end function recuperaLastCMP

	
	/*
	* Solicitud CAE y fecha de vencimiento 
	*/	
	public function FECAESolicitar($cbte, $ptovta, $regfe, $regfeasoc, $regfetrib, $regfeiva)
	{
	$params = array( 
		'Auth' => 
		array( 'Token' => $this->TA->credentials->token,
				'Sign' => $this->TA->credentials->sign,
				'Cuit' => self::CUIT ), 
		'FeCAEReq' => 
		array( 'FeCabReq' => 
			array( 'CantReg' => 1,
					'PtoVta' => $ptovta,
					'CbteTipo' => $regfe['CbteTipo'] ),
		'FeDetReq' => 
		array( 'FECAEDetRequest' => 
			array( 'Concepto' => $regfe['Concepto'],
					'DocTipo' => $regfe['DocTipo'],
					'DocNro' => $regfe['DocNro'],
					'CbteDesde' => $cbte,
					'CbteHasta' => $cbte,
					'CbteFch' => $regfe['CbteFch'],
					'ImpNeto' => $regfe['ImpNeto'],
					'ImpTotConc' => $regfe['ImpTotConc'], 
					'ImpIVA' => $regfe['ImpIVA'],
					'ImpTrib' => $regfe['ImpTrib'],
					'ImpOpEx' => $regfe['ImpOpEx'],
					'ImpTotal' => $regfe['ImpTotal'], 
					'FchServDesde' => $regfe['FchServDesde'], //null
					'FchServHasta' => $regfe['FchServHasta'], //null
					'FchVtoPago' => $regfe['FchVtoPago'], //null
					'MonId' => $regfe['MonId'], //PES 
					'MonCotiz' => $regfe['MonCotiz'], //1 
					'Tributos' => 
						array( 'Tributo' => 
							array ( 'Id' =>  $regfetrib['Id'], 
									'Desc' => $regfetrib['Desc'],
									'BaseImp' => $regfetrib['BaseImp'], 
									'Alic' => $regfetrib['Alic'], 
									'Importe' => $regfetrib['Importe'] ),
							), 
					'Iva' => 
						array ( 'AlicIva' => 
							array ( 'Id' => $regfeiva['Id'], 
									'BaseImp' => $regfeiva['BaseImp'], 
									'Importe' => $regfeiva['Importe'] ),
							), 
					), 
			), 
		), 
	);
	
	$results = $this->client->FECAESolicitar($params);

    $e = $this->_checkErrors($results, 'FECAESolicitar');
	
	//asigno respuesta 
	$resp_cae = $results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE;
	$resp_caefvto = $results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAEFchVto;

	return $e == false ? Array( 'cae' => $resp_cae, 'fecha_vencimiento' => $resp_caefvto ): false;
	} //end function FECAESolicitar
	
} // class

?>
