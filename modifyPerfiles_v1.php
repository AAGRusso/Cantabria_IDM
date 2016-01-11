<?php
/*
* Modifica los perfiles desde un archivo .CSV
* El formato del CSV es:
* Aplicacion,Perfil,Descripción,Quorum,CodBahia,Comprobación,plataforma
*/

/* version: 1 */
/* Change Log:
*/

date_default_timezone_set( 'Europe/Madrid' );
ini_set("display_errors", 1);
ini_set("track_errors", 1);
ini_set("html_errors", 1);
//error_reporting(E_ALL);
//set_error_handler('handlePhpErrors');

//set_error_handler("var_dump");

$VERSION="1.0";

##### 
$WS_PROT="http";
$WS_HOST="172.30.22.154";

//$WS_HOST="w2k12idm";

$WS_PORT="8180";
$WS_APP = "/IDMProv";
$WS_SERV="/role/service";
//$WS_SERV="/provisioning/service";

$WS_WSDL=$WS_SERV."?wsdl";
$WS_USR="uaadmin";
$WS_PASS="novell_1";
#####
$DEBUG=1;					// 0=No - 1=Error - 2=Info - 3=Verbose - 4=Debug  5=Completo
$LOG_DISPLAY="file";		// scr - file - all
$LOG_FILE="log-modifyPerfiles.log";
$SOAP_DEBUG=false;
#####
$APP_NAME="modifyPerfiles_v".$VERSION;
$DELIM=",";
$CHARSET="WIN";
$CHARSET="UTF";
$SEPARADOR=".";

#####
$ORGANISMO="00";
$CONSEJERIA="05";

$ROLECONTEXT="cn=Level10,cn=RoleDefs,cn=RoleConfig,cn=AppConfig,cn=User Application Driver,cn=driverset1,o=system";
$PRFCONTEXT="cn=Perfiles,";
$RSCCONTEXT="cn=Aplicaciones,cn=ResourceDefs,cn=RoleConfig,cn=AppConfig,cn=User Application Driver,cn=driverset1,o=system";

$url_wsdl=$WS_PROT."://".$WS_HOST.":".$WS_PORT.$WS_APP.$WS_WSDL;


myLog("--->Inicio:",4,"main");
$opciones = getopt("f:");
if (!$opciones) {
	die("Falta archivo de entrada\n"."Sintaxis: ".$APP_NAME." -f [archivo de entrada]\n");
}
/*
#if ( count($opciones) < 2 ) {
#	die("Sintaxis: ".$APP_NAME." -f [archivo de entrada] -r [registro a leer(0 para todos)]\n");
#}
*/


$file_name = $opciones["f"];

myLog("Archivo CSV:".$file_name,4,"");

$handle = fopen($file_name,"r");
if (!$handle) { myLog("No se puede abrir ".$file_name,1,""); die(); }
// Se espera que la primera línea sea el nombre de los campos y no datos, por lo que se desecha esa línea
myLog("Descartando cabecera:".$file_name,5,"");
fgetcsv($handle);
$contador=2;
myLog("Iniciando llamada SOAP",5,"");

if (!$SOAP_DEBUG) {
	$stub_prov = getSoapStub( $url_wsdl, $WS_USR, $WS_PASS );
}

myLog("Comienzo lectura archivo:".$file_name,5,"");

while (!feof($handle)) {

	$data=fgetcsv($handle,0,$DELIM);
	if ($CHARSET=="WIN") {	// Si se ha generado con Windows, cambiamos a UTF8
		myLog("Seleccionado CHARSET WIN",3,"");
		$APP1			= iconv("ISO-8859-15","UTF-8",$data[0]);
		$Perfil1		= iconv("ISO-8859-15","UTF-8",$data[1]);
		$DescBreve		= iconv("ISO-8859-15","UTF-8",$data[2]);
		$Quorum			= iconv("ISO-8859-15","UTF-8",$data[3]);
		$rpt			= iconv("ISO-8859-15","UTF-8",$data[4]);
		$unidad			= iconv("ISO-8859-15","UTF-8",$data[5]);
		$plataforma		= iconv("ISO-8859-15","UTF-8",$data[6]);
	} else {
		$APP1			= $data[0];
		$Perfil1		= $data[1];
		$DescBreve		= $data[2];
		$Quorum			= $data[3];
		$rpt			= $data[4];
		$unidad			= $data[5];
		$plataforma		= $data[6];
	} // conversion de datos
	
	
	myLog(">>>>>Leído registro :".$contador,4,"");
	myLog("\$APP1         : $APP1",5,"");
	myLog("\$Perfil1      : $Perfil1",5,"");
	myLog("\$DescBreve    : $DescBreve",5,"");
	myLog("\$Quorum       : $Quorum,",5,"");
	myLog("\$rpt          : $rpt",5,"");
	myLog("\$unidad       : $unidad",5,"");
	myLog("\$plataforma   : $plataforma",5,"");
	myLog("---------------",5,"");
	//print_r($data);


	// ** Comprobación de los datos
	// Eliminar caracteres extraños del nombre
	$APP = sanear_string($APP1);
	$Perfil = sanear_string($Perfil1);
	$description = trim($DescBreve);
	$description = (trim($description) == "" ? "Pendiente" : $description);
	$description = $description."|es~".$description;

	// **
	// ** Generación arrays
	// **
	// Lista de aprobadores	
		//$approver=array('approverDN'=>"cn=uaadmin,ou=sa,o=data", 'sequence'=>0);
		//$grantApprovers=array($approver);
	// Lista de propietarios, la aplicación no ncesita, así que ponemos al admin
	// El propietario lo asignamos a la UNIDAD que nos pasan

	if ( trim($rpt)  == "" ) {
		$propietario="cn=uaadmin,ou=sa,o=data";
	} else {
		$propietario = "ou=" . $rpt . ",ou=admreg,ou=Organizacion,ou=Auxiliar,o=data";
	}
	$dnstring=array('dn'=>$propietario);
	$owners=array($dnstring);

	// Generación de Categorías
	// Calculamos la consejería
	$CONSEJERIA=substr($rpt,0,2);
	// Calculamos la plataforma
	switch ($plataforma) {
		case "Unix/Natural":
			$plat="UN";
			break;
		case "Web/AMAP":
			$plat="WA";
			break;
		case "AD":
			$plat="M1";
			break;
		case "Housing";
			$plat="HO";
			break;
		default:
			$plat="ZZ";
	}

	$categoria1=$ORGANISMO.$CONSEJERIA;
	$categoria2 = $plat;

	$categorykey1=array('categoryKey'=>$categoria1);
	$categorykey2=array('categoryKey'=>$categoria2);
	//$categorykey3=array('categoryKey'=>"soap");
	//$resourceCategoryKeys=array($categorykey1,$categorykey2,$categorykey3);
	$roleCategoryKeys=array($categorykey1,$categorykey2);

	// dn de la aplicación
	$entityKey="cn=".$APP.$SEPARADOR.$Perfil.",".$PRFCONTEXT.$ROLECONTEXT;
//en~ACEOLIVC.ACEOLIVA
	$role=array(
	'description'=>$description,
	'entityKey'=>$entityKey,
	#'grantApprovers'=>$grantApprovers,
//	'name'=>$APP.$SEPARADOR.$Perfil."|es~".$APP.$SEPARADOR.$Perfil,
	'name'=>$APP.$SEPARADOR.$Perfil,
	'container'=>"cn=Perfiles",
	'owners'=>$owners,
	'quorum'=>"100",
	'systemRole'=>"false",
	'roleCategoryKeys'=>$roleCategoryKeys,
	'roleLevel'=>"10"
	);
	$result = "";

	if ($DEBUG == 5) {
		print_r($role);
	}

	if (!$SOAP_DEBUG) {
		if ( trim($Perfil) != "" ) {
			$result = modifyRole($stub_prov,$role);
		} else {
			myLog("Error registro sin Nombre: ".$contador,1,"");
		}
	} else {
		print_r($role);
		echo "-----------------------------------------\n";
		myLog("modo SOAP DEBUG:".$contador,1,"");
	}

/*
	// Si se ha creado el recurso
	if ( $result) {
		// Generamos la llamada para crear la asociación con la Aplicación
		$roleDN="cn=".$APP.$SEPARADOR.$Perfil.",".$PRFCONTEXT.$ROLECONTEXT;
		$rscDN = "cn=".$APP.",".$RSCCONTEXT;

		$localizedvalue1=array(
		'locale'=>"en",
		'value'=>"Perfil de la aplicacion ".$APP);
		$localizedvalue2=array(
		'locale'=>"en",
		'value'=>"Perfíl de la aplicación ".$APP);

		//$localli1=array('localizedvalue'=>$localizedvalue1);
		//$localli2=array('localizedvalue'=>$localizedvalue2);
		$LocalizedDescriptions= array('localizedvalue'=>$localizedvalue1);
		
		//		$localli1,$localli2);

		$assoc = array(
//		'entityKey'
		'approvalOverride'=>"false",
		'localizedDescriptions'=>$LocalizedDescriptions,
		'resource'=>$rscDN,
		'role'=>$roleDN,
		'status'=>"50"
		);
		//print_r($assoc);

		$result2=false;
		if (!$SOAP_DEBUG) {
			$result2=createResourceAssociation($stub_prov,$assoc);
		}
		if (!$result2) {
			myLog("Error generando asociación: ".$roleDN,1,"");
		}
	}
*/
	$contador++;
}





// initialize/get IDM SOAP stub with html header
function getSoapStub( $wsdl, $user, $pwd )
{
	myLog($wsdl."-".$user."-".$pwd,4,"getSoapStub");
	$stub = null;
	try
		{ $stub = @new SoapClient( $wsdl, array('login'=> $user,'password'=> $pwd,'exceptions' => true ) );	}
	catch ( Exception $exception )
		{ myLog("Error SOAP:".$exception->__toString(),1,"getSoapStub");}	
return( $stub );
}

function modifyRole ($wsObj,$role) {
	//use_soap_error_handler (false);
	$result = "";
	try
	{ $response = $wsObj->modifyRole(array('role'=> $role) );
		//var_dump($response);
		$result=true;
		myLog("modifyRole OK: ".$role['entityKey'],0,""); }
	catch ( SoapFault $exception )
		{ //var_dump($exception);
			//echo "\n\n\n\n\n\n\n\n\n\n============================================\n\n";
			//echo $exception->faultstring."\n";
			//echo $exception->faultcode."\n";
			//echo $exception->faultcodens."\n";
			//echo $exception->detail->NrfServiceException->reason."\n";
			//echo $exception->faultstring."\n";
			myLog("createRole ErrorSOAP:".$exception->__toString(),1,"createRole");
			myLog("createRole ErrorSOAP:".$exception->faultstring." (".$exception->faultcode.") ".$exception->detail->NrfServiceException->reason,0,"createRole");
			//die();
			$result = false;
			}
return($result);
}

function createResourceAssociation ($wsObj,$asso) {
	//use_soap_error_handler (false);
	$result = "";
	try
	{ $response = $wsObj->createResourceAssociation(array('resourceAssociation'=> $asso) );
		//var_dump($response);
		$result=true;
		myLog("createResourceAssociation OK: ".$asso['role'],0,""); }
	catch ( SoapFault $exception )
		{ //var_dump($exception);
			//echo "\n\n\n\n\n\n\n\n\n\n============================================\n\n";
			//echo $exception->faultstring."\n";
			//echo $exception->faultcode."\n";
			//echo $exception->faultcodens."\n";
			//echo $exception->detail->NrfServiceException->reason."\n";
			//echo $exception->faultstring."\n";
			myLog("createResrouceAssociation ErrorSOAP:".$exception->__toString(),1,"createResourceAssociation");
			myLog("createResourceAssociation ErrorSOAP:".$exception->faultstring." (".$exception->faultcode.") ".$exception->detail->NrfServiceException->reason,0,"createResrouceAssociation");
			//die();
			$result = false;
			}
return($result);
}







##############################################################
function handlePhpErrors($errno, $errmsg, $filename, $linenum, $vars) {
		var_dump($errmsg);
    if (stristr($errmsg, "SoapClient::SoapClient")) {
         error_log($errmsg); // silently log error
         return; // skip error handling
    }
}

function myLog($data,$level,$FUNC) {
// Genera log de la aplicación
// $data = texto a escribir
// $level nivel de log del texto
global $DEBUG,$LOG_DISPLAY,$LOG_FILE,$APP_NAME;
if ( $DEBUG>0 ) {
	if ($level <= $DEBUG) {
		$fecha = date("d-m-Y");
		$hora = date("H:i:s");
		$msg = "[$fecha]"."[$hora]"."[".$APP_NAME."][".$FUNC."][".$level."]-".$data;
		$order = array("\r\n", "\n", "\r");
		$msg1 = str_replace($order, " ", $msg);
		switch ($LOG_DISPLAY) {
			case "all":
				$handleLog = fopen($LOG_FILE,"a");
				if (!$handleLog) { echo "Error no se puede escribir en el log\n"; }
				fwrite($handleLog,$msg1."\n");
				fclose($handleLog);
				echo $msg."\n";
				break;
			case "scr":
				echo $msg."\n";
				break;
			case "file":
				$handleLog = fopen($LOG_FILE,"a");
				if (!$handleLog) { echo "Error no se puede escribir en el log\n"; return false; }
				fwrite($handleLog,$msg1."\n");
				fclose($handleLog);
				break;
			default:
				break;
		}
	} else { return true; }
} else { return true; }

return true;
} // end function myLog


/** * Reemplaza todos los acentos por sus equivalentes sin ellos *
 * @param $string
 * string la cadena a sanear *
 * @return $string
 * string saneada */

function sanear_string($string) {
	$string = trim($string);
	$string = str_replace( array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'), array('a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'), $string );
	$string = str_replace( array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'), array('e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'), $string );
	$string = str_replace( array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'), array('i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'), $string );
	$string = str_replace( array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'), array('o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'), $string );
	$string = str_replace( array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'), array('u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'), $string );
	$string = str_replace( array('ñ', 'Ñ', 'ç', 'Ç'), array('n', 'N', 'c', 'C',), $string );
	//Esta parte se encarga de eliminar cualquier caracter extraño
	$string = str_replace( array("\\", "¨", "º", "-", "~", "#", "@", "|", "!", "\"", "·", "$", "%", "&", "/", "(", ")", "?", "'", "¡", "¿", "[", "^", "`", "]", "+", "}", "{", "¨", "´", ">“, “< ", ";", ",", ":", "."), '', $string );
	$string = str_replace( array(" "), '_', $string );
	return $string;
}

?> 
