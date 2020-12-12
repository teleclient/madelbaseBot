<?php

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';

class EventHandler extends \danog\MadelineProto\EventHandler
{
    public function __construct($MadelineProto)
    {
        parent::__construct($MadelineProto);
    }

    public function report(string $message)
    {
        try {
            $this->messages->sendMessage(['peer' => self::ADMIN, 'message' => $message]);
        } catch (\Throwable $e) {
            $this->logger("While reporting: $e", Logger::FATAL_ERROR);
        }
    }

    public function onUpdateNewChannelMessage($update)
    {
        yield $this->onUpdateNewMessage($update);
    }
    public function onUpdateNewMessage($update)
    {
        if ($update['message']['_'] === 'messageEmpty') {
            return;
        }
        //if (isset($update['message']['out']) && $update['message']['out']) {
        //    return;
        //}

        //$res = json_encode($update, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        //if ($res == '') {
        //    $res = var_export($update, true);
        //}
        //$this->echo($res.PHP_EOL);

        $fromId = $update['message']['from_id'];
        $users  = yield $this->users->getUsers(['id' => [$fromId]]);
        $user   = sizeof($users)? $users[0] : null;
        $vou    = json_encode($user, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($vou == '') {
            $vou = var_export($user, true);
        }
        yield $this->echo($vou.PHP_EOL);

        if ($update['message']['message'] == '/start') {
            $replyKeyboardMarkup = [
                '_'      => 'replyKeyboardMarkup',
                'resize' => true,
                'rows'   => [
                    [
                        '_'       => 'keyboardButtonRow',
                        'buttons' => [
                            ['_'  => 'keyboardButton', 'text' => '💎 ارتقا سطح'],
                            ['_'  => 'keyboardButton', 'text' => '📖 راهنما']
                        ]
                    ],
                    [
                        '_'       => 'keyboardButtonRow',
                        'buttons' => [
                            ['_'  => 'keyboardButton', 'text' => 'پیشنهادات و انتقادات']
                        ]
                    ]
                ]
            ];
            yield $this->messages->sendMessage([
                'peer'         => $update,
                'message'      => 'به ربات من خوش آمدید.' .
                                  ' فایل خود را اینجا فوروارد کنید تا لینک مستقیم آن را دریافت کنید',
                'reply_markup' => $replyKeyboardMarkup
            ]);
        }

        if (isset($update['message']['media']) &&
                    ($update['message']['media']['_'] === 'messageMediaPhoto' ||
                    $update['message']['media']['_'] === 'messageMediaDocument'))
        {
            try {
                yield $this->messages->sendMessage([
                    'peer' => $update,
                    'message' => 'فایل شما در حال آپلود می باشد. لطفا کمی صبر کنید...'
                ]);
                $time = microtime(true);
                $file = yield $this->downloadToDir($update, './tmp');
                $link = str_replace('/home/mahtitel/public_html', $siteaddress, $file);
                yield $this->messages->sendMessage([
                    'peer' => $update,
                    'message' => 'فایل شما در مسیر ' . PHP_EOL . $link . PHP_EOL . 
                                 ' در ' . ceil(microtime(true) - $time) . ' ثانیه آپلود شد', 
                    'reply_to_msg_id' => $update['message']['id']
                ]);
            } catch (\danog\MadelineProto\RPCErrorException $e) {
            }
        }
    }
}

if (file_exists('MadelineProto.log')) {unlink('MadelineProto.log');}
$settings['logger']['logger_level'] = \danog\MadelineProto\Logger::ULTRA_VERBOSE;
$settings['logger']['logger']       = \danog\MadelineProto\Logger::FILE_LOGGER;

$MadelineProto = new \danog\MadelineProto\API('bot.madeline', $settings);
$MadelineProto->async(true);

while (true) {
    try {
        $MadelineProto->loop(function () use ($MadelineProto) {
            yield $MadelineProto->start();
            yield $MadelineProto->setEventHandler('\EventHandler');
        });
        $MadelineProto->loop();
    } catch (\Throwable $e) {
        try {
            $MadelineProto->logger("Surfacd: $e");
            $MadelineProto->getEventHadler(['async' => false])->report("Surfacd: $e");
        } catch (\Throwable $e) {
        }
    }
}
