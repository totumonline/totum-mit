<?php


namespace totum\common\configs;

use PHPMailer\PHPMailer\PHPMailer;

trait WithPhpMailerTrait
{
    abstract protected function getDefaultSender();

    public function sendMail($to, $title, $body, $attachments = [], $from = null, $replyTo = null, $hcopy = null)
    {
        list($body, $attachments) = $this->mailBodyAttachments($body, $attachments);

        try {
            $mail = new PHPMailer(true);

            $mail->SMTPDebug = $this->env !== static::ENV_LEVELS['production'];
            $mail->CharSet = 'utf-8';


            if (($smtpData = $this->getSettings('custom_smtp_setings_for_schema')) && is_array($smtpData)) {
                $mail->isSMTP();
                $mail->Host = $smtpData['host'] ?? '';
                $mail->Port = $smtpData['port'] ?? '';

                if ($mail->SMTPAuth = !empty($smtpData['login'])) {
                    $mail->Username = $smtpData['login'];
                    $mail->Password = $smtpData['password'] ?? $smtpData['pass'] ?? '';
                }

                foreach ($smtpData as $k=>$v){
                    if (str_starts_with($k, 'mail_')){
                        $param = substr($k, 5);
                        $mail->$param = $v;
                    }
                }

            } else {
                $mail->isSendmail();
            }


            $from = $from ?? $this->getDefaultSender();
            //Recipients
            $mail->setFrom($from, $from);
            foreach ((array)$to as $_to) {
                $mail->addAddress($_to);     // Add a recipient
            }

            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }
            if ($hcopy) {
                foreach ((array) $hcopy as $_h){
                    $mail->addBCC($_h);
                }
            }

            foreach ($attachments as $innrName => $fileString) {
                if (preg_match('/jpg|gif|png$/', $innrName)) {
                    $mail->addStringEmbeddedImage($fileString, $innrName, $innrName);
                } else {
                    $mail->addStringAttachment($fileString, $innrName);
                }
            }
            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $title;
            $mail->Body = $body;
            try {
                return $mail->send();
            } catch (\Exception) {
                return $mail->send();
            }
        } catch (\Exception $e) {
            throw new \ErrorException($mail->ErrorInfo);
        }
    }

}
