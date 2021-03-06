<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
//require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once 'image.php';
require_once  'src/Resource.php';
require_once  'src/Api.php';

use Allegro\REST\Api;
header("Content-type: text/xml");
//
//KONFIGURACJA
//
const clientID = 'nope';
const clientSECRET = 'nope';
const tokenFILE = 'token.json';
const offerLIMIT = '100'; //Maximum is 120x100 = 12000 offers
//
//KONFIGURACJA: KONIEC
//
//POBIERANIE ATRYBUTÓW SZUKANIA
//
$searchPhrase = null;
$excludePhrase = null;
$searchMode = null;
$categoryId = null;
$price_from = null;
$price_to = null;
$offerType = null;
$sellerId = null;
$offerCondition = null;

if (isset($_GET['string']) && strlen($_GET['string']) > 1) {
    //$searchPhrase = htmlspecialchars($_GET['string']);
    $rawString = $_GET['string'];
    $searchPhrase = str_replace(' ', '+', $rawString);
}
if (isset($_GET['exclude'])) {
    $excludePhrase = "";
    $rawString = $_GET['exclude'];
    $excludeArray = explode(' ', $rawString);
    
    foreach ($excludeArray as $excludeWord) {
        $excludePhrase .= '+-' . $excludeWord;
    }
}
if (isset($_GET['description']) && $_GET['description'] == 1) {
    $searchMode = "DESCRIPTIONS";
}
else if (isset($_GET['closed']) && $_GET['closed'] == 2) {
    $searchMode = "CLOSED";
}
else {
    $searchMode = "REGULAR";
}

if (isset($_GET['categoryId'])) {
    $categoryId = $_GET['categoryId'];
}
if (isset($_GET['sellerId'])) {
    $sellerId = $_GET['sellerId'];
}

if ((isset($_GET['price_from']) && is_numeric($_GET['price_from'])) || (isset($_GET['price_to']) && is_numeric($_GET['price_to']))) {
    
    if (isset($_GET['price_from']) && is_numeric($_GET['price_from'])) {
        
        $price_from = $_GET['price_from'];
    }
    
    if (isset($_GET['price_to']) && is_numeric($_GET['price_to'])) {
        
        $price_to = $_GET['price_to'];
    }
}


if (isset($_GET['offerType']) && is_numeric($_GET['offerType'])) {
    $gotofferType = $_GET['offerType'];
    
    if ($gotofferType == 1) {
        $offerType = "BUY_NOW";
    } 
    else if ($gotofferType == 2) {
        $offerType = "AUCTION";
    }
    else if ($gotofferType == 3) {
        $offerType = "ADVERTISEMENT";
    }
}

if (isset($_GET['offerCondition'])) {
    
    $offerCondition = $_GET['offerCondition'];
}
//
//

$printLineSeparator = "<br>\n";
$errorMsgHeader = "<b>API response error</b>" . $printLineSeparator;

$api = new Api(clientID, clientSECRET, null, null, null, null);
$responseAuthJSON = $api->checkAccessTokenDevice(tokenFILE);

$responseAuthHeader = $responseAuthJSON['headers'];

if ($responseAuthHeader != null && getHttpStatus($responseAuthJSON['headers'][0]) != "200") {
    echo $errorMsgHeader;
    $responseError = json_decode($responseAuthJSON['content'], true);
    echo getErrorData($responseError, $printLineSeparator);
}
else {
    $stepCounter = offerLIMIT < 100 ? 1 : round_up(offerLIMIT / 100, 0);
    $offerCounter = offerLIMIT;
    
    $rssTitle1 = null;
    $rssTitle2 = null;
    if($searchPhrase != null) {
        $rssTitle1 = "Wyniki wyszukiwania: " . $searchPhrase;
    }
    if($categoryId != null) {
        if ($rssTitle1 != null) {
            $rssTitle2 = " w kategorii: " . $categoryId;
        }
        else {
            $rssTitle2 = "Kategoria: " . $categoryId;
        }
    }
    
    $rssHeader = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    $rssHeader .= "<rss version=\"2.0\">\n";
    $rssHeader .= "<channel>\n";
    $rssHeader .= "<title>Allegro.pl - RSS: " . $rssTitle1 . $rssTitle2 . "</title>\n";
    $rssHeader .= "<link>https://allegro.pl</link>\n";
    $rssHeader .= "<description>" . $rssTitle1 . $rssTitle2 . " - najnowsze oferty.";
    $rssHeader .= "</description>\n";
    $rssFooter = "</channel>\n";
    $rssFooter .= "</rss>";
    
    echo $rssHeader;
    echo $price_to;
    for ($i = 0; $i < $stepCounter; $i++) {
        $queryOffset = $i == 0 ? 0 : 100 * $i + 1; 
        if ($offerCounter > $queryOffset) {
            $data = array(
                'sort'=> '-startTime',
                'fallback' => 'false',
                'include' => '-all',
                'include' => 'items',
                'limit' => '100',
                'offset' => $queryOffset,
                'searchMode' => $searchMode,
                'sellingMode.format' => $offerType,
                'phrase' => $searchPhrase . $excludePhrase,
                'category.id' => $categoryId,
                'seller.id' => $sellerId,
                'price.from' => $price_from,
                'price.to' => $price_to,
                'parameter.11323' => $offerCondition
            );
            
            $responseJSON = $api->offers->listing->get($data);
            $httpStatus = getHttpStatus($responseJSON['headers'][0]);

            if ($httpStatus == "200") {
                $offers = json_decode($responseJSON['content']);
                $offerCounter = $offers->searchMeta->availableCount;
                echo getOfferData($offers->items->promoted, 1);
                echo getOfferData($offers->items->regular, 0);
            }
            else {
                $responseError = json_decode($responseJSON['content'], true);
                
                echo $errorMsgHeader;
                if (getArrayDepth($responseError) == 2) {
                    //blad zapytania API
                    foreach ($responseError as $errArray) {
                        foreach ($errArray as $errArrayItem) {
                            foreach ($errArrayItem as $errType => $errValue) {
                                echo "<b>" .$errType . ":</b> " . $errValue . $printLineSeparator;
                            }
                        }
                    }
                }
                else {
                    //blad autoryzacji
                    echo getErrorData($responseError, $printLineSeparator);
                }
            }
        }

    }
    echo $rssFooter;
}


function round_up($number, $precision = 2)
{
    $fig = (int) str_pad('1', $precision, '0');
    return (ceil($number * $fig) / $fig);
}

/**
 * @param string $httpHeader
 * @return string
 */
function getHttpStatus($httpHeaderResponse) {
    $httpHeaderArr = explode(' ', $httpHeaderResponse);
    $httpStatus = $httpHeaderArr[1];
    return $httpStatus;
}

/**
 * @param array $array
 * @return int
 */
function getArrayDepth($array) {
    $depth = 0;
    $iteIte = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));
    
    foreach ($iteIte as $ite) {
        $d = $iteIte->getDepth();
        $depth = $d > $depth ? $d : $depth;
    }
    
    return $depth;
}

/**
 * @param array $responseError
 * @return string
 */
function getErrorData($responseError, $lineSeparator = '') {
    $errorString = "";
    foreach ($responseError as $errArrayKey => $errArrayValue) {
        $errorString .= "<b>" .$errArrayKey . ":</b> " . $errArrayValue . $lineSeparator;
        }
    return $errorString;
}

/**
 * @param object $offerObject
 * @param integer $offerPromoted
 * @return string
 */
function getOfferData($offerObject, $offerPromoted = 0, $printLineSeparator = "<br>\n") {
    $domain = $_SERVER['SERVER_NAME'];
    $path = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], "/"));
    $rss = "";
    for ($i = 0; $i < count($offerObject); $i++) {
        $offerSellingMode = $offerObject[$i]->sellingMode;
        $rss .= "<item>\n";
        $rss .=  "<title>";
        $rss .= $offerPromoted == 1 ? "(PROMOWANE) " : "";
        $rss .= htmlspecialchars($offerObject[$i]->name) . " - "  . (float)$offerSellingMode->price->amount . "zł" . "</title>\n";
        $rss .=  "<link>https://allegro.pl/i" . $offerObject[$i]->id . ".html</link>\n";
        $rss .=  "<description><![CDATA[\n";
        if (count($offerObject[$i]->images) > 0) {
            $rss .=  "<a href=\"https://allegro.pl/i" . $offerObject[$i]->id . ".html\"><img src=\"http://".$domain . $path ."/image.php?url=" . $offerObject[$i]->images[0]->url . "\" style='float: left; max-height: 128px; max-width: 128px;'></a>\n";
        }
        $rss .= "<a href=\"https://allegro.pl/i" . $offerObject[$i]->id . ".html\">Link do aukcji</a>" . $printLineSeparator;
        if (isset($offerObject[$i]->seller)) {
            $sellerInfo = "Sprzedawca: <a href='https://allegro.pl/uzytkownik/" .  $offerObject[$i]->seller->id . "/oceny'>" . $offerObject[$i]->seller->id . "</a>";
            $sellerInfo .= $offerObject[$i]->seller->company == TRUE ? ", Firma" : "";
            $sellerInfo .= $offerObject[$i]->seller->company == TRUE ? ", SuperSprzedawca" : "";
        }
        else {
            $sellerInfo = "";
        }
        
        $rss .= $sellerInfo . $printLineSeparator;
        
        $offerSellingMode = $offerObject[$i]->sellingMode;
        if ($offerSellingMode->format == "BUY_NOW") {
            
            if (isset($offerSellingMode->fixedPrice)) {
                $offerType = "Licytacja" . $printLineSeparator;
                $offerPrice = "Aktualna cena: " . $offerSellingMode->price->amount . " " . $offerSellingMode->price->currency  . $printLineSeparator;
                $offerPrice .= "Cena: " . $offerSellingMode->fixedPrice->amount . " " . $offerSellingMode->price->currency  . $printLineSeparator;
            }
            else {
                $offerType = "Kup Teraz" . $printLineSeparator;
                $offerPrice = "Cena: " . $offerSellingMode->price->amount . " " . $offerSellingMode->price->currency . $printLineSeparator;
            }
        }
        elseif ($offerSellingMode->format == "AUCTION") {
            $offerType = "Licytacja" . $printLineSeparator;
            $offerPrice =  "Aktualna cena: " . $offerSellingMode->price->amount . " " . $offerSellingMode->price->currency  . $printLineSeparator;
        }
        else {
            $offerType = "Ogłoszenie" . $printLineSeparator;
            $offerPrice =  "Cena: " . $offerSellingMode->price->amount . " " . $offerSellingMode->price->currency  . $printLineSeparator;
        }
        $rss .= $offerType . $offerPrice;
        
        if (isset($offerObject[$i]->publication)) {
            $endingTime = datetimeRFC($offerObject[$i]->publication->endingAt);
        }
        else {
            $endingTime = "do wyczerpania przedmiotów";
        }
        $rss .= "Zakończenie: " . $endingTime . $printLineSeparator;
              
        $rss .=  "]]></description>\n";
        $rss .=  "<guid isPermaLink=\"false\">" . $offerObject[$i]->id . "</guid>\n";
        $rss .=  "</item>\n";
    }
    return $rss;
}
function datetimeRFC($dateREST){
    //Converts Y-m-d\Tg:i:s.u\Z format to RFC 2822 format
    $dateArray = explode('.', $dateREST);
    
    $dateTimeObject = DateTime::createFromFormat('Y-m-d\TH:i:s', $dateArray[0]);
    $dateTime = date_format($dateTimeObject, 'r');
    return $dateTime;
}
?>