<?php

$urlNewProductsCat = 'https://www.electronictoolbox.com/category/83118/new-products/';
$urlUPGCat = 'https://www.electronictoolbox.com/category/53387/universal-power-group/';

function curlGet($ch, $url)
{
    curl_setopt($ch, CURLOPT_URL, $url);
    @curl_setopt($ch, CURLOPT_GET, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest']);

    return curl_exec($ch);
}

function getPagesCount($ch, $url)
{
    $data = curlGet($ch, $url.'?page=1');

    $dataPageCount = json_decode($data, true)['page_count'];

    $doc = new DOMDocument();
    $doc->loadHTML($dataPageCount);
    $xpath = new DOMXpath($doc);
    $elementsClassCount= $xpath->query("//span[@class='count']");
    $elementsClassFull = $xpath->query("//span[@class='full']");

    if (!isset($elementsClassCount) || !isset($elementsClassFull)) return 0;

    return ceil((int)$elementsClassFull[0]->childNodes[0]->nodeValue/(int)$elementsClassCount[0]->childNodes[0]->nodeValue);
}

function getProductsJSON($url)
{
    $ch = curl_init();
    $category = $url;
    $pagesCount = getPagesCount($ch, $url);

    $products = array();

    for ($currentPage = 1; $currentPage <= $pagesCount; $currentPage++)
    {
        $data = curlGet($ch,$url.'?page='.$currentPage);
        $dataContent = json_decode($data, true)['content'];
        $doc = new DOMDocument();
        @$doc->loadHTML($dataContent);
        $xpath = new DOMXPath($doc);

        $elements = $xpath->query(".//div[contains(@class,'item product')]");
        foreach($elements as $element)
        {
            //sku
            $other_nodes = $xpath->query(".//div[@class='sku show-for-large']", $element);
            $other_nodes = $xpath->query(".//span[@class='value']", $other_nodes[0]);
            $other_nodes = $xpath->query(".//span[@itemprop='sku']", $other_nodes[0]);
            $sku = $other_nodes[0]->nodeValue;

            //name
            $name = $element->getAttribute('data-name');

            //description
            $other_nodes = $xpath->query(".//div[@class='description show-for-medium']", $element);
            $other_nodes = $xpath->query(".//span[@itemprop='description']", $other_nodes[0]);
            $description = $other_nodes[0]->nodeValue;

            //category
            //echo $category;

            //images
            $images = array();
            $other_nodes = $xpath->query(".//div[@class='image_container container']", $element);
            $other_nodes = $xpath->query(".//div[contains(@class,'images')]/img", $other_nodes[0]);
            foreach ($other_nodes as $image_node)
            {
                array_push($images, array(
                    "link" => $image_node->getAttribute('data-src')
                ));
            }

            //brand
            $brand = $element->getAttribute('data-brand');

            //stock
            $stock = 'In stock';
            $other_nodes = $xpath->query(".//div[@class='overflow_container']", $element);
            $other_nodes = $xpath->query(".//div[contains(@class,'out-of-stock')]", $other_nodes[0]);
            if (!empty($other_nodes[0]->nodeValue)) $stock = 'Out of stock';

            //price
            $oldPrice = $element->getAttribute('data-list-price');
            $currentPrice = (string)json_decode($element->getAttribute('data-prices'), true)['1'];
            $price_with_discount = '';
            $default_price = '';
            if (empty($oldPrice)) $default_price = $currentPrice;
            else
            {
                $price_with_discount = $currentPrice;
                $default_price = $oldPrice;
            }

            $product = array(
                "sku" => $sku,
                "name" => $name,
                "description" => $description,
                "category" => $category,
                "images" => $images,
                "brand" => $brand,
                "stock" => $stock,
                "default_price" => $default_price,
                "price_with_discount" => $price_with_discount,
            );

            array_push($products, $product);
        }
        echo $currentPage.' of '.$pagesCount.' pages is parsed'.PHP_EOL;
    }
    curl_close($ch);
    echo 'function getProductsJSON finished'.PHP_EOL;
    return json_encode($products);
}

file_put_contents('feedNewProducts.json', getProductsJSON($urlNewProductsCat));
file_put_contents('feedUPG.json', getProductsJSON($urlUPGCat));

?>