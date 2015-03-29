<?php

class TumblrPhotoFetch {

    private $version,
            $user_agent,
            $location,
            $feed,
            $log_file,
            $log_handle,
            $client,
            $downloads;

    function __construct($url, $picture_path, $log_path = '') {
        
        $this->version = '0.3';
        $this->user_agent = sprintf('TumblrPhotoFetch/%s (https://github.com/mcdado/TumblrPhotoFetch)', $this->version);
        $this->client = new http\Client;
        $this->feed = $url;
        $this->location = $picture_path;

        if (!file_exists($picture_path)) {
            mkdir($picture_path, 0777, true);
        }
        
        if ($log_path == '') {
            $this->log_file = '';
        } else {
            $this->log_file = $log_path;
            $this->log_handle = fopen($this->log_file, 'a') or die("Cannot open log file");
        }
        
    }

    function terminate() {

        $this->sendLog("Done.");

        if ($this->log_handle) {
            fclose($this->log_handle);
        }
    }

    private function sendLog($message) {
        if ($this->log_file == '') {
            echo $message . PHP_EOL;
        } else {
            fwrite($this->log_handle, sprintf('[%1$s] %2$s', date('Y-m-d H:i:s'), $message.PHP_EOL));
        }
    }

    public function init() {

        $this->sendLog(sprintf("%s started.", $this->user_agent));

        $request = new http\Client\Request('GET', $this->feed, array('User-Agent' => $this->user_agent));
        $request->addQuery(new http\QueryString('type=photo'));

        if (file_exists($this->location . '/feed.rss')) {
            $mod_date = filemtime($this->location . '/feed.rss');
            $request->setHeader('If-Modified-Since', gmdate('D, d M Y H:i:s \G\M\T', $mod_date)); // RFC 2616 formatted date
        }

        try {
            $this->client->enqueue($request)->send();
            $response = $this->client->getResponse($request);
            if ($response->getResponseCode() == 200) {
                $body = $response->getBody();
                file_put_contents($this->location . '/feed.rss', $body);
                $parsed_body = simplexml_load_string($body);
                unset($body);

                foreach ($parsed_body->posts->post as $entry) {
                    $extracted_link = '';
                    $extracted_link = (string)$entry->{'photo-url'}[0];
                    if ($extracted_link != '') {
                        $redirection = $this->getRedirectUrl($extracted_link);
                        $clean_link = $redirection ? $redirection : $extracted_link;
                        $this->downloads[] = $clean_link;
                    }
                }
                unset($parsed_body);
            } else {
                $this->sendLog(sprintf("Feed Response Code: %d", $response->getResponseCode()));
                return;
            }
        } catch (http\Exception $ex) {
            $this->sendLog(sprintf("Feed Raised Exception: %s", $ex));
            return;
        }

        $this->sendLog("Beginning to fetch links.");
        $this->fetchLinks();
    }

    private function getRedirectUrl($url) {
        stream_context_set_default(array(
            'http' => array(
                'method' => 'HEAD'
            )
        ));
        try {
            $headers = get_headers($url, 1);
            $this->sendLog(sprintf("Looking for redirection on %s", $url));
            if ($headers !== false && isset($headers['Location'])) {
                $this->sendLog(sprintf("└─> Redirected to %s", $headers['Location']));
                return $headers['Location'];
            }
        } catch (Exception $ex) {
            $this->sendLog("Couldn't get redirected URL.");
        }
        return false;
    }

    private function fetchLinks() {

        foreach ($this->downloads as $k => $link ) {
            // Normalizing the URL early, updating it in place.
            $this->downloads[$k] = str_replace(' ', '%20', $link);

            $parsed_link = parse_url($link);
            if ($parsed_link == false) {
                $this->sendLog(sprintf("Problem with link: %s (skipping)", $link));
                unset($this->downloads[$k]);
                continue;
            }

            $file_name = basename($parsed_link['path']);
            if ( file_exists($this->location . '/' . $file_name) ) {
                unset($this->downloads[$k]);
            }
        }

        foreach ($this->downloads as $d_link) {
            $d_request = new http\Client\Request('GET', $d_link, array('User-Agent' => $this->user_agent));
            try {
                $this->client->enqueue($d_request)->send();
                $d_name = basename($d_request->getRequestUrl());
                $d_response = $this->client->getResponse();
                if ($d_response->getResponseCode() == 200) {
                    file_put_contents($this->location . DIRECTORY_SEPARATOR . $d_name, $d_response->getBody());
                    $this->sendLog(sprintf("Succesfully downloaded %s", $d_name) );

                } else {
                    $this->sendLog(sprintf("%s reported a status code: %d", $file_name, $d_response->getResponseCode()) );

                }
            } catch (http\Exception $ex) {
                $this->sendLog(sprintf("Raised Exception: %s", $ex));
            }
        }
    }
}
