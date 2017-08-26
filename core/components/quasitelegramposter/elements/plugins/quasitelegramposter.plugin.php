<?php
/**
 * @author <https://quasi-art.ru>
 * @version 1.0
 * 26.08.2017
 */

$apiHost = 'https://api.telegram.org/';
$token = '123456789:AwesomeTokenForBot';
$chatId = '@aliasofchat';
// Ресурсы с этими шаблонами будут публиковаться на канале
$articlesTemplates = [3];
$siteUrl = $modx->getOption('site_url');
// На тот случай, когда HTTPS есть, но MODX всё равно генерирует ссылки в HTTPS
$siteUrl = str_replace('http://', 'https://', $siteUrl);
$debug = false;

/**
 * Публикация ресурсов в случае обычной публикации, 
 * публикации по расписанию и при сохранении уже опубликованного ресурса
 */
switch ($modx->event->name) {
    case 'OnDocPublished':
    case 'OnResourceAutoPublished':
    case 'OnDocFormSave':
        // При сохранении неопубликованного ресурса ничего не делать
        if ($modx->event->name === 'OnDocFormSave' && !$resource->get('published')) {
            break;
        }
        // При создании нового ресурса его идентификатор надо брать из переменной $id
        $resourceId = ($modx->event->name === 'OnDocFormSave') ? $id : $resource->get('id');
        // При создании ресурса приходится генерировать URI ресурса таким образом
        if ($modx->event->name === 'OnDocFormSave' && $mode === 'new') {
            $uri = $siteUrl.$resource->get('uri');
        } else {
            $uri = $modx->makeUrl($resourceId, '', '', 'https');
        }

        $format = 1;

    	if (in_array($resource->get('template'), $articlesTemplates)) {
    	    
    	    switch ($format) {
    	        // Формат — простая ссылка
    	        case 0:
    	            $message = $uri;
    	            $apiUri = $apiHost.'bot'.$token.'/sendMessage?chat_id='.$chatId.'&parse_mode=HTML&text='.$message;
    	            break;
    	        case 1:
            	    // Формат — Изображение с подписью (заголовок, описание и ссылка)
            	    // Относительный путь до изображения
            	    $image = $siteUrl.$resource->getTVValue('image');
            	    // На тот случай, когда HTTPS есть, но MODX всё равно генерирует ссылки в HTTPS
                    $uri = str_replace('http://', 'https://', $uri);
            	    $imageCaption = urlencode(
            	        $resource->get('pagetitle').
            	        "\n\n".
            	        $resource->get('description').
            	        "\n\n".
            	        $uri
            	    );
            	    $apiUri = $apiHost.'bot'.$token.'/sendPhoto?chat_id='.$chatId.'&photo='.$image.'&caption='.$imageCaption;
            	    break;
            	default:
            	    break;
    	    }

		    $response = file_get_contents($apiUri);

            if ($debug) {
    	        $modx->log(xPDO::LOG_LEVEL_ERROR, $apiUri);
    	        $modx->log(xPDO::LOG_LEVEL_ERROR, $response);
            }
    	}
    	break;
    default:
    	break;
}