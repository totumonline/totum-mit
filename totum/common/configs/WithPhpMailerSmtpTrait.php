<?php


namespace totum\common\configs;

use PHPMailer\PHPMailer\PHPMailer;
use totum\common\errorException;

trait WithPhpMailerSmtpTrait
{
    abstract protected function getDefaultSender();

    public function sendMail($to, $title, $body, $attachments = [], $from = null, $replyTo = null, $hcopy = null)
    {
        list($body, $attachments) = $this->mailBodyAttachments($body, $attachments);

        try {
            $mail = new PHPMailer(true);

            $mail->SMTPDebug = $this->env !== static::ENV_LEVELS["production"];
            $mail->isSMTP();

            if (!property_exists($this, 'SmtpData') || empty($this->SmtpData) || empty($this->SmtpData['host'] || empty($this->SmtpData['port']))){
                throw new errorException($this->translate('Fill in the parameter [[%s]]', 'Conf.php SmtpData'));
            }


            $mail->Host = $this->SmtpData['host'];
            $mail->Port = $this->SmtpData['port'];

            if ($mail->SMTPAuth = !empty($this->SmtpData['login'])) {
                $mail->Username = $this->SmtpData['login'];
                $mail->Password = $this->SmtpData['pass'];
            }
            $mail->CharSet = 'utf-8';

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
