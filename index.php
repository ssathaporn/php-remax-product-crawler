<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

while (ob_get_level() > 0)
    ob_end_flush();

function getCategories($url) {
	global $dir;

	$result = file_get_contents($url);
	output('loading `' . $url . '`...');
	preg_match_all('/<nav role=\"navigation\" class=\"navbar navbar\-default navbar\-fixed\-top nav\-shadow\">(.*?)<\/nav>/s', $result, $navbar);
	preg_match_all('/<ul role=\"menu\" class=\"dropdown\-menu\">(.*?)<\/ul>/s', $navbar[1][0], $dropdowns);

	preg_match_all('/<li(.*?)>(.*?)<\/li>/', $dropdowns[1][0], $categories);
	$result = $categories[2];
	output('getting categories...');
	
	foreach ($result as $key => $value) {
		$name = strtolower(strip_tags($value));
		$name = str_replace(' ', '-', $name);
		$name = preg_replace('/[^A-Za-z0-9\-]/', '', $name);
		
		if ($name) {
			$dir = '';
			createDir($name); 
			preg_match_all('/href="(.*?)"/', $value, $link);
			$categoryUrl = $link[1][0];
			getProducts($categoryUrl);
		}
	}
}

function getProducts($url) {
	output('getting product list `' . $url . '`...');
	$result = file_get_contents($url);
	preg_match_all('/<div class="pinBox pinWell margin-bottom-15">(.*?)<\/div>/', $result, $productCard);
	$products = $productCard[1];

	foreach($products as $key => $value) {
		preg_match_all('/<a href="(.*?)">/', $value, $link);
		$product= $link[1][0];
		createDir(($key +1));
		getProductDetail($product);
	}
}

function getProductDetail($link) {
	global $url;

	$link = $url . $link;
	output('getting product detail `' . $link . '`...');
	$cleanUrl = explode('/product/', $link);
	$cleanUrl[1] = urlencode($cleanUrl[1]);
	$link = implode('/product/', $cleanUrl);
	$result = file_get_contents($link);
	saveInfo($result, $link);
}

function saveInfo($result, $link) {
	global $path, $dir;
	
	preg_match_all('#<div class="col-xs-3 col-sm-3 col-md-3">(.*?)<img src="(.*?)"(.*?)>(.*?)</div>#s', $result, $div);
	$thumbnail = $div[2];

	preg_match_all('/img.remaxthailand.co.th\/100x100\/product\/(.*?)\//s', $thumbnail[0], $code);
	$product_code = $code[1];

	foreach($thumbnail as $key => $value) {
		$value = str_replace('100x100', '500x500', $value);
		
		$file = file_get_contents($value);
		$tmp = explode('/', $value);
		$fileName = array_pop($tmp);
		file_put_contents($path . $dir . DIRECTORY_SEPARATOR . 'thumbnail-' . $fileName, $file);
		output("\tdownload thumbnail..");
	}

	preg_match_all('/<h1 class="font-24 margin-top-10">(.*)<\/h1>/', $result, $info);
	$productName = ($info[1] && $info[1][0]) ? strip_tags($info[1][0]) : '';
	
	preg_match_all('/<h2 class="font-16 margin-top-0">(.*)<\/h2>/', $result, $info);
	$productModel = ($info[1] && $info[1][0]) ? strip_tags($info[1][0]) : '';
	
	preg_match_all('/<div class="clearfix"><\/div>(.*)<div class="row margin-top-10">(.*)<\/div>/s', $result, $info);
	$productInfo = '';

	if ($info[2] && $info[2][0]) {
		$content = $info[2][0];
		$productInfo = strip_tags($content);
		$productInfo = str_replace('ราคาปลีก', PHP_EOL . 'ราคาปลีก',  $productInfo);
		$productInfo = str_replace('ราคาปกติ', PHP_EOL . 'ราคาปกติ',  $productInfo);
		$productInfo = str_replace('ราคาส่ง', PHP_EOL . 'ราคาส่ง',  $productInfo);
		$productInfo = str_replace('มีสินค้าจัดส่ง', PHP_EOL . 'มีสินค้าจัดส่ง',  $productInfo);
		$productInfo = str_replace('สินค้าหมด', PHP_EOL . 'สินค้าหมด',  $productInfo);
		$productInfo = str_replace('จัดส่งสินค้าภายใน', PHP_EOL . 'จัดส่งสินค้าภายใน',  $productInfo);
		$productInfo = str_replace('รายละเอียดเกี่ยวกับสินค้า', PHP_EOL . PHP_EOL . 'รายละเอียดเกี่ยวกับสินค้า' . PHP_EOL,  $productInfo);
		$productInfo = str_replace('คุณสมบัติพิเศษ', PHP_EOL .PHP_EOL . 'คุณสมบัติพิเศษ' . PHP_EOL,  $productInfo);
		$productInfo = str_replace('วิธีใช้งาน', PHP_EOL . PHP_EOL . 'วิธีใช้งาน' . PHP_EOL,  $productInfo);

		$productInfo = str_replace('		Copyright © 2015 Remax (Thailand) All rights reserved. ', '', $productInfo);
	}

	preg_match_all('/<img(.*?)data-original="(.*?)"(.*?)>/s', $result, $images);
	$productImages = '';

	if ($images[2]) {
		$img = $images[2];

		foreach($img as $key => $value) {
			
			$file = file_get_contents($value);
			$tmp = explode('/', $value);
			$fileName = array_pop($tmp);
			file_put_contents($path . $dir . DIRECTORY_SEPARATOR . $fileName, $file);
			$productImages .= $fileName . PHP_EOL;
			output("\tdownload product image..");
		}
	}

	$product = 'link: ' . $link . PHP_EOL .
		$productName . PHP_EOL .
		$productModel . PHP_EOL . PHP_EOL .
		$product_code[0] . PHP_EOL .
		$productInfo . PHP_EOL . PHP_EOL .
		$productImages;
	file_put_contents($path . $dir . DIRECTORY_SEPARATOR . 'info.txt', $product, FILE_APPEND | LOCK_EX);

	$dir = dirname($dir);
	output('product downloaded.');
}

function createDir($name) {
	global $path, $dir;

	if (!is_dir($path)) {
		if (!mkdir($path)){
			die('Failed to create folders...');
		}
	}

	$link = $path . $dir . DIRECTORY_SEPARATOR . $name;

	if (!is_dir($link)) {
		if (!mkdir($link)){
			die('Failed to create folders...');
		}

		output('create folder `' . $name . '`');
	} else {
		output('existing folder `' . $name . '`');
	}

	$dir .= DIRECTORY_SEPARATOR . $name ;
}

function deleteDir($dir) {
	if (is_dir($dir)) {
		foreach(scandir($dir) as $file) {
			if ('.' === $file || '..' === $file) {
				continue;
			}

			if (is_dir("$dir/$file")) {
				deleteDir("$dir/$file");
			} else {
				unlink("$dir/$file");
			}
		}

		rmdir($dir);
	}
}

function output($str) {
	echo $str . PHP_EOL;
}

output('start... (' . date('Y-m-d') . ')');

$path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'remax';
$dir = '';

output('prepare...');
deleteDir($path);

$url = 'https://www.remaxthailand.co.th';
getCategories($url);
output('finish!');
