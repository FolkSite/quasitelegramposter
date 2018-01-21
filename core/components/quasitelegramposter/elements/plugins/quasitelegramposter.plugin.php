<?php
/**
 * @author <https://quasi-art.ru>
 * @version 1.0
 * 26.08.2017
 */

/**
 * Конфигурация
 */
$config = [
    // Ресурсы только с этими шаблонами будут публиковаться на канале
    'articlesTemplates' => [3],
    'chatId' => '@aliasofchat',
    'debug' => false,
    'forceHTTPS' => true,
    /**
     * 0 — простая ссылка
     * 1 — изображение с подписью (заголовок, описание и ссылка)
     */
    'format' => 1,
    'imageTV' => 'image',
    'skipSendingTV' => 'dontSendToTelegram',
    'token' => '123456789:AwesomeTokenForBot',
];

$siteUrl = $modx->getOption('site_url');
$siteUrl = $config['forceHTTPS'] ? str_replace('http://', 'https://', $siteUrl) : $siteUrl;
$scheme = $config['forceHTTPS'] ? 'https' : 'full';
$apiHost = 'https://api.telegram.org/';

if (!function_exists('quasiTelegramNotifierDebug')) {
	function quasiTelegramNotifierDebug($message) {
		global $modx;
		global $config;
		if ($config['debug']) {
			$modx->log(xPDO::LOG_LEVEL_ERROR, $message);
		}
	}
}

/**
 * Публикация ресурсов в случае обычной публикации,
 * публикации по расписанию и при сохранении уже опубликованного ресурса
 */
quasiTelegramNotifierDebug('quasiTelegramNotifier');
quasiTelegramNotifierDebug('event: '.$modx->event->name);
switch ($modx->event->name) {
	case 'OnDocPublished':
	case 'OnResourceAutoPublish':
	case 'OnDocFormSave':
		// При сохранении неопубликованного ресурса ничего не делать
		if ($modx->event->name === 'OnDocFormSave' && !$resource->get('published')) {
			break;
		}
		// При создании нового ресурса его идентификатор надо брать из переменной $id
		$resourceId = null;
		// При создании ресурса приходится генерировать URI ресурса таким образом
		if ($modx->event->name === 'OnDocFormSave' && $mode === 'new') {
			$resourceId = $id;
			$uri = $siteUrl.$resource->get('uri');
			if (!in_array($resource->get('template'), $config['articlesTemplates'])) {
				quasiTelegramNotifierDebug('Ne tot shablon');
				break;
			}
		} else if ($modx->event->name === 'OnResourceAutoPublish') {
			if (
				isset($results) &&
				is_array($results) &&
				isset($results['published_resources']) &&
				is_array($results['published_resources']) &&
				count($results['published_resources']) > 0
				) {
				quasiTelegramNotifierDebug('results below:');
				quasiTelegramNotifierDebug(print_r($results, true));
				$resourceId = $results['published_resources'][0]['id'];
			}
			$uri = $modx->makeUrl($resourceId, '', '', $scheme);
			$resource = $modx->getObject('modResource', $resourceId);
			quasiTelegramNotifierDebug('uri: '.$uri);
		} else {
			$resourceId = $resource->get('id');
			$uri = $modx->makeUrl($resourceId, '', '', $scheme);
		}

		// Чтобы избежать повторной отправки в канал
		$dontSendToTelegram = (int)$resource->getTVValue($config['skipSendingTV']);
		if ($dontSendToTelegram === 0) {
			$resource->setTVValue($config['skipSendingTV'], 1);
			$resource->save();
		} else if ($dontSendToTelegram === 1) {
			quasiTelegramNotifierDebug('Ne nuzhno otpravlyat');
			break;
		}
		switch ($config['format']) {
			case 0:
				$apiUri = $apiHost.'bot'.$config['token'].'/sendMessage?chat_id='.$config['chatId'].'&parse_mode=HTML&text='.$uri;
				break;
			case 1:
				$image = $siteUrl.$resource->getTVValue($config['imageTV']);
				$uri = ($config['forceHTTPS']) ? str_replace('http://', 'https://', $uri) : $uri;
				$imageCaption = urlencode(
					$resource->get('pagetitle').
					"\n\n".
					$resource->get('description').
					"\n\n".
					$uri
				);
				$apiUri = $apiHost.'bot'.$config['token'].'/sendPhoto?chat_id='.$config['chatId'].'&photo='.$image.'&caption='.$imageCaption;
				break;
			default:
				break;
		}

		$response = file_get_contents($apiUri);

		quasiTelegramNotifierDebug('REQUEST URI: '.$apiUri);
		quasiTelegramNotifierDebug('response: '.$response);
		break;
	default:
		break;
}
