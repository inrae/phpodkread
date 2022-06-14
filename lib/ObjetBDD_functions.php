<?php

/**
 * Experimental : utilisable uniquement avec les dernieres vesrsions de php (> 5.3)
 * @author Eric Quinton
 * 18 aout 2009
 */

 /**
  * Function to connect at a database
  *
  * @param array $param
  * @return pdo
  */
function connect($dsn, $user, $password, $schema = "public")
{
    try {
        $pdo = new Pdo($dsn, $user, $password);
        if (isset($schema)) {
            $pdo->exec("set search_path=" . $schema);
        }
        return $pdo;
    } catch (PDOException $e) {
        throw new ObjetBDDException(($e->getMessage()));
    }
}
function objetBDDparamInit()
{
    global $ObjetBDDParam, $DEFAULT_formatdate, $OBJETBDD_debugmode, $FORMATDATE;
    if (!isset($DEFAULT_formatdate)) {
        $DEFAULT_formatdate = "fr";
    }
    if (!isset($OBJETBDD_debugmode)) {
        $OBJETBDD_debugmode = 1;
    }
    /**
     * Preparation des parametres pour les classes heritees de ObjetBDD
     */
    if (!isset($ObjetBDDParam)) {
        $ObjetBDDParam = array();
    }
    if (!is_array($ObjetBDDParam)) {
        $ObjetBDDParam = array();
    }
    if (isset($FORMATDATE)) {
        $ObjetBDDParam["formatDate"] = $FORMATDATE;
    } else {
        $ObjetBDDParam["formatDate"] = $DEFAULT_formatdate;
    }

    $ObjetBDDParam["debug_mode"] = $OBJETBDD_debugmode;
    $_SESSION["ObjetBDDParam"] = $ObjetBDDParam;
}

/**
 * function _new
 * initialisation d'une classe basee sur ObjetBDD,
 * avec passage des parametres adequats
 * 
 * @param
 *            $classe
 * @return instance
 */
function _new($classe)
{
    return new $classe($bdd, $ObjetBDDParam);
}

/**
 * _ecrire
 * execution de la fonction ecrire sur l'instance $instance,
 * declaree precedemment avec la fonction _new.
 * Affiche les messages d'erreur le cas echeant
 * Retourne le resultat de la fonction d'ecriture.
 * 
 * @param
 *            $instance
 * @param
 *            $data
 * @return unknown_type
 */
function _ecrire($instance, $data)
{
    $rep = $instance->ecrire($data);
    //$instance->getErrorData(1);
    return $rep;
}
