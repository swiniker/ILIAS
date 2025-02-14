<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * Class arException
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @author  Timon Amstutz <timon.amstutz@ilub.unibe.ch>
 * @version 2.0.7
 */
class arException extends ilException implements \Stringable
{
    public const UNKNONWN_EXCEPTION = -1;
    public const COLUMN_DOES_NOT_EXIST = 1001;
    public const COLUMN_DOES_ALREADY_EXIST = 1002;
    public const RECORD_NOT_FOUND = 1003;
    public const GET_UNCACHED_OBJECT = 1004;
    public const LIST_WRONG_LIMIT = 1005;
    public const LIST_ORDER_BY_WRONG_FIELD = 1006;
    public const LIST_JOIN_ON_WRONG_FIELD = 1007;
    public const COPY_DESTINATION_ID_EXISTS = 1008;
    public const PRIVATE_CONTRUCTOR = 1009;
    public const FIELD_UNKNOWN = 1010;
    protected static array $message_strings = [
        self::UNKNONWN_EXCEPTION => 'Unknown Exception',
        self::COLUMN_DOES_NOT_EXIST => 'Column does not exist:',
        self::COLUMN_DOES_ALREADY_EXIST => 'Column does already exist:',
        self::RECORD_NOT_FOUND => 'No Record found with PrimaryKey:',
        self::GET_UNCACHED_OBJECT => 'Get uncached Object from Cache:',
        self::LIST_WRONG_LIMIT => 'Limit, to value smaller than from value:',
        self::LIST_JOIN_ON_WRONG_FIELD => 'Join on non existing field: ',
        self::COPY_DESTINATION_ID_EXISTS => 'Copy Record: A record with the Destination-ID already exists.',
        self::PRIVATE_CONTRUCTOR => 'Constructor cannot be accessed.',
        self::FIELD_UNKNOWN => 'Field Unknown.'
    ];
    /**
     * @var string
     */
    protected $message = '';

    /**
     * @param int $code
     * @param string $additional_info
     */
    public function __construct(protected $code = self::UNKNONWN_EXCEPTION, protected $additional_info = '')
    {
        $this->assignMessageToCode();
        parent::__construct($this->message, $this->code);
    }

    protected function assignMessageToCode(): void
    {
        $this->message = 'ActiveRecord Exeption: ' . self::$message_strings[$this->code] . $this->additional_info;
    }

    public function __toString(): string
    {
        return implode('<br>', [$this::class, $this->message]);
    }
}
