<?php


namespace totum\common\Json;

use JsonStreamingParser\Listener\InMemoryListener;
use JsonStreamingParser\Parser;
use JsonStreamingParser\Exception\ParsingException;
use JsonStreamingParser\ParserHelper;
use totum\common\errorException;

class TotumJson extends TotumJsonParcer
{
    const STATE_IN_TOTUM_PARAM = 14;
    protected $TotumParamStarts;
    /**
     * @var callable
     */
    protected $totumCalculate;
    /**
     * @var \Closure
     */
    protected $stringCalculate;

    /**
     * @var int
     */
    public function __construct($string, $listener = null)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $string);
        rewind($stream);

        $this->listener = new InMemoryListener();
        parent::__construct($stream, $this->listener);
    }

    public function setTotumCalculate($func)
    {
        $this->totumCalculate = $func;
    }

    public function getJson()
    {
        return $this->listener->getJson();
    }

    public function setStringCalculate(\Closure $func)
    {
        $this->stringCalculate = $func;
    }

    /**
     * @throws ParsingException
     */
    protected function startValue(string $c): void
    {
        if ('[' === $c) {
            $this->startArray();
        } elseif ('{' === $c) {
            $this->startObject();
        } elseif ('"' === $c) {
            $this->startString();
        } elseif (ParserHelper::isDigit($c)) {
            $this->startNumber($c);
        } elseif ('t' === $c) {
            $this->state = self::STATE_IN_TRUE;
            $this->buffer .= $c;
        } elseif ('f' === $c) {
            $this->state = self::STATE_IN_FALSE;
            $this->buffer .= $c;
        } elseif ('n' === $c) {
            $this->state = self::STATE_IN_NULL;
            $this->buffer .= $c;
        } elseif ('#' === $c || '$' === $c || '@' === $c) {
            $this->buffer .= $c;
            $this->state = self::STATE_IN_TOTUM_PARAM;
            $this->TotumParamStarts = ['ultimate' => "", '[' => 0];
        } else {
            $this->throwParseError('Unexpected character for value: ' . $c);
        }
    }

    protected function consumeChar(string $char): void
    {
        // see https://en.wikipedia.org/wiki/Byte_order_mark
        if ($this->charNumber < 5
            && 1 === $this->lineNumber
            && $this->checkAndSkipUtfBom($char)
        ) {
            return;
        }

        // valid whitespace characters in JSON (from RFC4627 for JSON) include:
        // space, horizontal tab, line feed or new line, and carriage return.
        // thanks: http://stackoverflow.com/questions/16042274/definition-of-whitespace-in-json
        if ((' ' === $char || "\t" === $char || "\n" === $char || "\r" === $char) &&
            !(self::STATE_IN_STRING === $this->state ||
                self::STATE_UNICODE === $this->state ||
                self::STATE_START_ESCAPE === $this->state ||
                self::STATE_IN_NUMBER === $this->state)
        ) {
            // we wrap this so that we don't make a ton of unnecessary function calls
            // unless someone really, really cares about whitespace.
            if ($this->emitWhitespace) {
                $this->listener->whitespace($char);
            }

            return;
        }

        switch ($this->state) {
            case self::STATE_IN_TOTUM_PARAM:
                $this->processTotumParam($char);
                break;
            case self::STATE_IN_STRING:
                if ('"' === $char) {
                    $this->endString();
                } elseif ('\\' === $char) {
                    $this->state = self::STATE_START_ESCAPE;
                } elseif ($char < "\x1f") {
                    $this->throwParseError('Unescaped control character encountered: ' . $char);
                } else {
                    $this->buffer .= $char;
                }
                break;

            case self::STATE_IN_ARRAY:
                if (']' === $char) {
                    $this->endArray();
                } else {
                    $this->startValue($char);
                }
                break;

            case self::STATE_IN_OBJECT:
                if ('}' === $char) {
                    $this->endObject();
                } elseif ('"' === $char) {
                    $this->startKey();
                } else {
                    $this->throwParseError('Start of string expected for object key. Instead got: ' . $char);
                }
                break;

            case self::STATE_END_KEY:
                if (':' !== $char) {
                    $this->throwParseError("Expected ':' after key.");
                }
                $this->state = self::STATE_AFTER_KEY;
                break;

            case self::STATE_AFTER_KEY:
                $this->startValue($char);
                break;

            case self::STATE_START_ESCAPE:
                $this->processEscapeCharacter($char);
                break;

            case self::STATE_UNICODE:
                $this->processUnicodeCharacter($char);
                break;

            case self::STATE_UNICODE_SURROGATE:
                $this->unicodeEscapeBuffer .= $char;
                if (2 === mb_strlen($this->unicodeEscapeBuffer)) {
                    $this->endUnicodeSurrogateInterstitial();
                }
                break;

            case self::STATE_AFTER_VALUE:
                $within = end($this->stack);
                if (self::STACK_OBJECT === $within) {
                    if ('}' === $char) {
                        $this->endObject();
                    } elseif (',' === $char) {
                        $this->state = self::STATE_IN_OBJECT;
                    } else {
                        $this->throwParseError("Expected ',' or '}' while parsing object. Got: " . $char);
                    }
                } elseif (self::STACK_ARRAY === $within) {
                    if (']' === $char) {
                        $this->endArray();
                    } elseif (',' === $char) {
                        $this->state = self::STATE_IN_ARRAY;
                    } else {
                        $this->throwParseError("Expected ',' or ']' while parsing array. Got: " . $char);
                    }
                } else {
                    $this->throwParseError(
                        'Finished a literal, but unclear what state to move to. Last state: ' . $within
                    );
                }
                break;

            case self::STATE_IN_NUMBER:
                if (ctype_digit($char)) {
                    $this->buffer .= $char;
                } elseif ('.' === $char) {
                    if (false !== strpos($this->buffer, '.')) {
                        $this->throwParseError('Cannot have multiple decimal points in a number.');
                    } elseif (false !== stripos($this->buffer, 'e')) {
                        $this->throwParseError('Cannot have a decimal point in an exponent.');
                    }
                    $this->buffer .= $char;
                } elseif ('e' === $char || 'E' === $char) {
                    if (false !== stripos($this->buffer, 'e')) {
                        $this->throwParseError('Cannot have multiple exponents in a number.');
                    }
                    $this->buffer .= $char;
                } elseif ('+' === $char || '-' === $char) {
                    $last = mb_substr($this->buffer, -1);
                    if (!('e' === $last || 'E' === $last)) {
                        $this->throwParseError("Can only have '+' or '-' after the 'e' or 'E' in a number.");
                    }
                    $this->buffer .= $char;
                } else {
                    $this->endNumber();
                    // we have consumed one beyond the end of the number
                    $this->consumeChar($char);
                }
                break;

            case self::STATE_IN_TRUE:
                $this->buffer .= $char;
                if (4 === \strlen($this->buffer)) {
                    $this->endTrue();
                }
                break;

            case self::STATE_IN_FALSE:
                $this->buffer .= $char;
                if (5 === \strlen($this->buffer)) {
                    $this->endFalse();
                }
                break;

            case self::STATE_IN_NULL:
                $this->buffer .= $char;
                if (4 === \strlen($this->buffer)) {
                    $this->endNull();
                }
                break;

            case self::STATE_START_DOCUMENT:
                $this->listener->startDocument();
                if ('[' === $char) {
                    $this->startArray();
                } elseif ('{' === $char) {
                    $this->startObject();
                } else {
                    $this->throwParseError('Document must start with object or array.');
                }
                break;

            case self::STATE_END_DOCUMENT:
                if ('[' !== $char && '{' !== $char) {
                    $this->throwParseError('Expected end of document.');
                }
                $this->state = self::STATE_START_DOCUMENT;
                $this->consumeChar($char);
                break;

            case self::STATE_DONE:
                $this->throwParseError('Expected end of document.');
                break;

            default:
                $this->throwParseError('Internal error. Reached an unknown state: ' . $this->state);
                break;
        }
    }

    protected function processTotumParam(string $char)
    {
        switch ($char) {
            case '"':
            case "'":
                break;
            case '[':
                $this->TotumParamStarts[$char]++;
                break;
            case ']':
                if ($this->TotumParamStarts["["]) {
                    $this->TotumParamStarts["["]--;
                } else {
                    $this->endTotum();
                    $this->consumeChar($char);
                    return;
                }
                break;
            case ':':
            case ',':
            case '}':
                $this->endTotum();
                $this->consumeChar($char);
                return;

        }
        $this->buffer .= $char;
    }

    protected function endString(): void
    {
        $popped = array_pop($this->stack);
        if (self::STACK_KEY === $popped) {
            $this->listener->key(($this->stringCalculate)($this->buffer));
            $this->state = self::STATE_END_KEY;
        } elseif (self::STACK_STRING === $popped) {
            $this->listener->value(($this->stringCalculate)($this->buffer));
            $this->state = self::STATE_AFTER_VALUE;
        } else {
            $this->throwParseError('Unexpected end of string.');
        }
        $this->buffer = '';
    }

    protected function endTotum()
    {
        $this->listener->value(($this->totumCalculate)($this->buffer));
        $this->state = self::STATE_AFTER_VALUE;
        $this->buffer = '';
    }
}
