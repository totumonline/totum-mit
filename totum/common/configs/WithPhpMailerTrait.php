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
                    $mail->Password = $smtpData['password'] ?? '';
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

            if($replyTo){
                $mail->addReplyTo($replyTo);
            }
            if($hcopy){
                $mail->addBCC($hcopy);
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
            return $mail->send();
        } catch (\Exception $e) {
            throw new \ErrorException($mail->ErrorInfo);
        }
    }

}
