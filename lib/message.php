<?php


class Message
{

    /**
     * Tableau contenant l'ensemble des messages generes
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

    function set($value, $is_error = false)
    {
        $this->_message[] = $value;
        if ($is_error) {
            $this->is_error = true;
        }
    }

    function setSyslog($value)
    {
        $this->_syslog[] = $value;
    }

    /**
     * Retourne le tableau brut
     */
    function get()
    {
        if ($this->_displaySyslog) {
            return array_merge($this->_message, $this->_syslog);
        } else {
            return $this->_message;
        }
    }

    /**
     * Retourne le tableau formate avec saut de ligne entre
     * chaque message
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
?>