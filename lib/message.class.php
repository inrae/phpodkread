<?php

/**
 * Store all messages in a array
 */
class Message
{

    /**
     * All messages
     *
     * @var array
     */
    private $_message = array();

    private $_syslog = array();

    private $_displaySyslog = false;

    public $is_error = false;

    function __construct($displaySyslog = false)
    {
        $this->_displaySyslog = $displaySyslog;
    }

    /**
     * Add a message
     *
     * @param string $value
     * @param boolean $is_error
     * @return void
     */
    function set(string $value, $is_error = false)
    {
        $this->_message[] = $value;
        if ($is_error) {
            $this->is_error = true;
        }
    }
    /**
     * Add a technical message
     * Perhaps used to fill into syslog messages
     *
     * @param string $value
     * @return void
     */
    function setSyslog(string $value)
    {
        $this->_syslog[] = $value;
    }

    /**
     * get all messages
     */
    function get(): array
    {
        if ($this->_displaySyslog) {
            return array_merge($this->_message, $this->_syslog);
        } else {
            return $this->_message;
        }
    }

    /**
     * Get all messages formated to html display
     *
     * @return string
     */
    function getAsHtml()
    {
        $data = "";
        $i = 0;
        if ($this->displaySyslog) {
            $tableau = array_merge($this->_message, $this->_syslog);
        } else {
            $tableau = $this->_message;
        }
        foreach ($tableau as $value) {
            if ($i > 0) {
                $data .= "<br>";
            }
            $data .= htmlentities($value);
            $i++;
        }
        return $data;
    }
}
