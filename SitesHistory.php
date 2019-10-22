<?php


/**
 * Class SitesHistory
 *
 */
class SitesHistory {

    protected $curl;
    protected static $instance;

    protected $map_sites = [
        'bet365' => 'bet365.com',
        'coral' => 'coral.co.uk',
        'ladbrokes' => 'ladbrokes.com',
        'paddy power' => 'paddypower.com',
        'william hill' => 'williamhill.com',
        'betway' => 'betway.com',
        'betfair' => 'betfair.com',
        'betfred' => 'betfred.com',
        'unibet' => 'unibet.com',
        'betdaq' => 'betdaq.com',
        'bwin' => 'bwin.com',
        'skybet' => 'skybet.com',
        'betvictor' => 'betvictor.com',
        'boylesports' => 'boylesports.com',
        'smarkets' => 'smarkets.com',
    ];

    protected $base_url = 'http://currentlydown.com/';

    protected function __construct() {
        $this->curl = curl_init();
    }

    protected function __clone() {}

    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function singleton() {
        return static::getInstance();
    }

    public function getSiteHistory($key) {
        $url = $this->map_sites[$key];

        if (!$url) {
            throw new Error('Site no found!');
        }

        return $this->getStats($key, $url);
    }

    public function getAllSitesHistory() {
        $result = [];
        foreach ($this->map_sites as $key => $url) {
            $result[$key] = $this->getStats($key, $url);
        }

        return $result;
    }

    protected function getStats($key, $site) {
        $cache_file = $this->getFile($key);

        if ($cache_file) {
            return $cache_file;
        }

        $url = $this->base_url . $site;

        $html = $this->download($url);

        $xml = $this->getSimpleXmlFromHtml($html);
        $recent_outages = $this->getRecentOutages($xml);

        $result_by_provider = [];
        foreach ($recent_outages as $outages) {
            $ul = $outages->ul;
            foreach ($ul->li as $li) {
                $data_span = $li->span;
                $date = str_replace('#chart-', '', trim((string)$data_span->a->attributes()->href));
                $downtime = formatDuration( trim((string)$data_span->span->small));
                $result_by_provider[$date] = $downtime;
            }
        }

        $data =  ['url' => $site, 'history' => $result_by_provider];

        if (!empty($result_by_provider)) {
            $this->saveProviderInfo($data, $key);
        }

        return $data;
    }


	protected function formatDuration( $str ) {
		$formatted_str = str_replace( '~', '', $str );
		$str_arr       = explode( ' ', trim( $formatted_str ) );
		$hours         = isset($str_arr[1]) ? intval( $str_arr[0]) : 0;
		$mins          = isset($str_arr[1]) ? intval( $str_arr[1] ) : intval( $str_arr[0] );

		if ( $mins % 5 !== 5 ) {
			$mins = $mins + ( 5 - $mins % 5);
		}

		$formatted_hours = $hours !== 0 ? $hours . 'h ' : '';

		$output = $formatted_hours . $mins . 'min';

		return $output;
	}



    protected function getFile($key) {
        $dirname = __DIR__ . '/downloads';
        $filename = $dirname . '/' . $key . '.json';


        if (!file_exists($filename)) {
            return null;
        }

        $update_date =  time() - filectime($filename);

        if (($update_date / 86400) > 1) {
            return null;
        }

        $file = file_get_contents($filename);

        if (!$file) {
            return null;
        }

        $content = json_decode($file, true);

        return $content;
    }

    protected function saveProviderInfo($data, $key) {
        $dirname = __DIR__ . '/downloads';
        $filename = $dirname . '/' . $key . '.json';
        !is_writable($dirname)
        and mkdir($dirname, 0777);

        $json_data = json_encode($data);
        file_put_contents($filename, $json_data);
    }

    protected function getCurlOptions() {
        return array(
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_USERAGENT		=> 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.19 (KHTML, like Gecko) Chrome/18.0.1025.142 Safari/535.19',
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => 1,

        );
    }

    protected function download($url) {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt_array($this->curl, $this->getCurlOptions());
        curl_getinfo($this->curl);
        $received_data = curl_exec($this->curl);
        return $received_data;
    }

    protected function getSimpleXmlFromHtml($html) {
        $document = new \DOMDocument(null, 'UTF-8');
        @$document->loadHTML($html);

        $xml = simplexml_import_dom($document);

        return $xml;
    }


    protected function getRecentOutages($xml) {
        $recent_outages = $xml->xpath('descendant::div[@class="row recent-outages"]');
        $recent_outages = reset($recent_outages);
        return $recent_outages;
    }
}