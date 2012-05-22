<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Xavier Perseguers <xavier@causal.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Hook for clearing cache on CloudFlare.
 *
 * @category    Hooks
 * @package     TYPO3
 * @subpackage  tx_cloudflare
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   Causal Sàrl
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id$
 */
class tx_cloudflare_tcemain {

	/** @var string */
	protected $extKey = 'cloudflare';

	/**
	 * Clears the CloudFlare cache.
	 *
	 * @param array $params
	 * @param t3lib_TCEmain $pObj
	 * @return void
	 */
	public function clear_cacheCmd(array $params, t3lib_TCEmain $pObj) {
		if ($params['cacheCmd'] !== 'all') {
			return;
		}

		$domain = t3lib_div::getIndpEnv('TYPO3_HOST_ONLY');
		$parameters = array(
			'a' => 'fpurge_ts',
			'z' => $domain,
			'v' => '1',
		);
		$ret = $this->sendCloudFlare($parameters);

		if (is_object($pObj->BE_USER)) {
			if ($ret['result'] === 'error') {
				$pObj->BE_USER->writelog(4, 1, 1, 0, 'User %s failed to clear the cache on CloudFlare (domain: "%s"): %s', array($pObj->BE_USER->user['username'], $domain, $ret['msg']));
			} else {
				$pObj->BE_USER->writelog(4, 1, 0, 0, 'User %s cleared the cache on CloudFlare (domain: "%s"): %s', array($pObj->BE_USER->user['username'], $domain, $ret['msg']));
			}
		}
	}

	/**
	 * Sends data to CloudFlare.
	 *
	 * @param array $additionalParams
	 * @return array
	 */
	protected function sendCloudFlare(array $additionalParams) {
		$config = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		$params = array(
			'tkn'   => $config['apiKey'],
			'email' => $config['email']
		);
		$allParams = array_merge($params, $additionalParams);

		return $this->POST('https://www.cloudflare.com/api_json.html', $allParams);
	}

	/**
	 * This methods POSTs data to CloudFlare.
	 *
	 * @param array $data
	 * @return array JSON payload returned by CloudFlare
	 * @throws RuntimeException
	 */
	protected function POST($url, array $data) {
		if (TRUE || $GLOBALS['TYPO3_CONF_VARS']['SYS']['curlUse'] == '1') {
			if (!function_exists('curl_init') || !($ch = curl_init())) {
				throw new RuntimeException('cURL cannot be used', 1337673614);
			}

			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, max(0, intval($GLOBALS['TYPO3_CONF_VARS']['SYS']['curlTimeout'])));
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

			if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyServer']) {
				curl_setopt($ch, CURLOPT_PROXY, $GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyServer']);

				if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyTunnel']) {
					curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, $GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyTunnel']);
				}
				if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyUserPass']) {
					curl_setopt($ch, CURLOPT_PROXYUSERPWD, $GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyUserPass']);
				}
			}

			if (!($result = curl_exec($ch))) {
				trigger_error(curl_errno($ch));
			}
			curl_close($ch);
			return json_decode($result, TRUE);
		} else {
			// TODO with fsockopen()
		}
	}

}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/cloudflare/hooks/class.tx_cloudflare_tcemain.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/cloudflare/hooks/class.tx_cloudflare_tcemain.php']);
}

?>