<?php


namespace totum\common\configs;

use PHPMailer\PHPMailer\PHPMailer;

trait WithPhpMailerTrait
{
    abstract protected function getDefaultSender(): string;

    public function sendMail($to, $title, $body, $attachments = [], $from = null)
    {
        list($body, $attachments) = $this->mailBodyAttachments($body, $attachments);

        try {
            $mail = new PHPMailer(true);

            $mail->SMTPDebug = $this->env !== static::ENV_LEVELS["production"];
            $mail->isSendmail();
            $mail->CharSet = "utf-8";

            $from = $from ?? $this->getDefaultSender();
            //Recipients
            $mail->setFrom($from, $from);
            foreach ((array)$to as $_to) {
                $mail->addAddress($_to);     // Add a recipient
            }

            foreach ($attachments as $innrName => $fileString) {
                if (preg_match('/jpg|gif|png$/', $innrName)) {
                    $mail->addEmbeddedImage($fileString, $innrName, $innrName);
                } else {
                    $mail->addAttachment($fileString, $innrName);
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
