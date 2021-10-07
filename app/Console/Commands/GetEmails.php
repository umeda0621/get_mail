<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client as Google_Client;
use Google\Service\Gmail as Google_Service_Gmail;
use GuzzleHttp\Client;
use PHPHtmlParser\Dom;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlMultiHandler;

class GetEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:getMail';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gmail受信';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
//        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . dirname(__FILE__) . '/client_secret_403552829341-r6mta883eivboaft3qv7fth3be2inr52.apps.googleusercontent.com.json');


        if (php_sapi_name() != 'cli') {
            throw new Exception('This application must be run on the command line.');
        }

        /**
         * Returns an authorized API client.
         * @return Google_Client the authorized client object
         */

        // Get the API client and construct the service object.
        $client = $this->getClient();
        $service = new Google_Service_Gmail($client);

        // Print the labels in the user's account.
        $user = 'me';

        $optParams = array(
            'maxResults' => 500,
            // Gmailのメール検索時の形式で条件を指定できます。
            'q' => 'from:mag@pointi.jp OR from:info@gendama.jp OR from:getmoney@dietnavi.com OR from:info@pointmail.rakuten.co.jp)',
//        'q'=>'gendama.jp'
        );

        while (true) {
            $messages = $service->users_messages->listUsersMessages($user, $optParams);
            if (count($messages) < 1) {
                break;
            }

//        echo count($messages) . "\n";
            foreach ($messages->getMessages() as $message) {
                $message_id = $message->getID();
                $message_contents = $service->users_messages->get($user, $message_id);

                $url_list = [];
                $parts = $message_contents->getPayload()->getParts();
                if (empty($parts)) {
                    $body = base64_decode(strtr($message_contents->getPayload()->body->data, '-_', '+/'));

                    if (preg_match_all('(https?://[-_.!~*\'()a-zA-Z0-9;/?:@&=+$,%#]+)', $body, $result) !== false) {
                        $url_list = $result[0];
                    }
                } else {
                    $body = base64_decode(strtr($parts[1]->body->data, '-_', '+/'));

                    $dom = new Dom;
                    $dom->loadStr($body);
                    $url_list = $dom->find('a');

                    echo json_encode($url_list);
                    return 0;
                }

                foreach ($url_list as $url) {
                    if (strpos($url, 'click') !== false) {
                        $client = new Client([
                            'request.params' => ['redirect.strict' => true]
                        ]);
//                        $client->getConfig()->set('request.params', [
//                            'redirect.strict' => true
//                        ]);
                        $client->request('GET', $url, [
                            'verify' => false,
//                            'curl' => [
//                                CURLOPT_RETURNTRANSFER => true,
//                                CURLOPT_SSL_CIPHER_LIST => 'AES256-SHA256',
////                                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
////
////                                CURLOPT_SSLVERSION=> 4,
////                                CURLOPT_SSLVERSION => CURL_SSLVERSION_SSLv3,
////                                CURLOPT_SSL_CIPHER_LIST, 'SSLv3'
//                            ]
                            'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.71 Safari/537.36',],
                        ]);
                    }
                }

                $service->users_messages->delete($user, $message->getId());

//                return 0;
            }
        }
        return 0;
    }

    private function getClient()
    {
        $client = new Google_Client();
        $client->setApplicationName('Gmail API PHP Quickstart');
        $client->setScopes(Google_Service_Gmail::MAIL_GOOGLE_COM);
        $client->setAuthConfig('credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        $tokenPath = 'token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }

}
