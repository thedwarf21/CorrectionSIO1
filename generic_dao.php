<?php

/**
 * La classe GenericDao est un connecteur/requêteur SQL générique.
 * Pour fonctionner, il a besoin que le dictionnaire de données de la base ciblée soit alimenté au sein d'une vue spécifique.
 * IMPORTANT: pour des raisons de sécurité, trouvez-lui un nom qui ne soit pas parlant du tout, car elle décrit votre modèle de données.
 * 
 * Le nom de la vue contenant ledit dictionnaire de données est paramétré via la constante de classe DB_DICTIONARY_TABLE.
 * La vue doit contenir les rubriques :
 *    TABLE_NAME =  nom de la table       --> NOT NULL
 *    COLUMN_NAME = nom de la rubrique    --> NOT NULL
 *    IS_ID_COL = booléen permettant d'identifier la rubriue en auto_increment, si la table en contient une.
 *    DATA_TYPE =   type de la donnée     --> 'string' | 'int' | 'float' | 'datetime'
 *    DATA_PARAMS = paramétrage lié à la donnée. Selon DATA_TYPE :
 *                                                  - 'string'    -> expression régulière de contrôle | NULL
 *                                                  - 'int'       -> NULL
 *                                                  - 'float'     -> nombre de chiffres après la virgule ou NULL
 *                                                  - 'datetime'  -> string de formatage. Ex: '%d/%m/%Y'
 *    FK_LINK  =    pour les clés étrangères, permet d'identifier la rubrique référencée et la valeur à afficher dans les SELECT
 *                à partir de la jointure, sous la forme 'TABLE:COLONNE:SQL_FOR_JOIN'. Sinon NULL.
 *                SQL_FOR_JOIN contient une expression SQL (pensez à préfixer les noms de champs du nom de la table contenante)
 *                Le code préfixera automatiquement avec le nom de la table
 *    
 * L'objet créé via cette classe est lié à une table, dont le nom doit être passé en paramètre au constructeur de la classe.
 * 
 * Possibilité d'héritage, pour ajouter des méthodes métier spécifiques ou enrichir/redéfinir certaines méthodes (quelle souplesse !! ;D)
 */
class GenericDao {

  /*** Constantes utilisée pour se connecter à la base de données (configuration) ***/

  // Configuration pour l'environnement de production
  /*const DB_HOST = "adresse.du.serveur";
  const DB_USER = "monUser";
  const DB_PASSWD = "M0n5up3rM0t2P@ss3DeL@M0rkitu";
  const DB_NAME = "awsomelyTooLongDbNameJustInOrderToTurnYouMadIHopeYouEnjoyByTheWay";
  const DB_PORT = 1234;*/

  // Configuration pour l'environnement de tests
  const DB_HOST = "localhost";
  const DB_USER = "root";
  const DB_PASSWD = "";
  const DB_NAME = "correction";
  const DB_PORT = 3306;
  const DB_CHARSET = "utf8";
  const DB_DICTIONARY_TABLE = "Dictionnaire";

  // Propriétés de la classe
  protected $table_name;
  protected $db_conn;
  protected $cols_infos;
  protected $fk_links;

  /**
   * Constructeur de la classe.
   * Instancie un objet permettant d'accéder à une table du modèle de données.
   * 
   * Alimente les informations des colonnes de la table sous forme de liste de tableaux associatifs ($this->cols_infos),
   * ainsi que la liste des références à des enregistrements externes à la table ($this->fk_links)
   * 
   * @param      string  $table_name  Nom de la table liée au connecteur.
   *
   * @throws     GenericDaoError Si le nom de la table n'est pas renseigné, ou si la connexion à la BdD échoue.
   */
  function __construct($table_name) {
    if (!$table_name)
      throw new GenericDaoError(null, null, "GenericDao::__construct() --> paramètre \$table_name non renseigné.");
    $this->table_name = $table_name;

    try { 
      $this->connect(); 
      $sql =  "SELECT COLUMN_NAME, IS_ID_COL, DATA_TYPE, DATA_PARAMS, FK_LINK " .
              "FROM " . GenericDao::DB_DICTIONARY_TABLE . " " .
              "WHERE TABLE_NAME = '" . $table_name . "'";
      $result = $this->query($sql);

      if ($result->num_rows === 0)              // Si aucun résultat, on jette une erreur
        throw new GenericDaoError($sql, null, "Dictionnaire de données absent pour la table $table_name");
      else {                                    // Sinon, on stocke les informations dans $cols_infos
        $current_index = 0;
        $this->cols_infos = [];
        $this->fk_links = [];

        while ($ligne = $result->fetch_assoc()) {
          if ($ligne["FK_LINK"]) {
            $ref = explode(":", $ligne["FK_LINK"]);
            array_push($this->fk_links, array(
              "FOR_INDEX" => $current_index,
              "TABLE_NAME" => $ref[0],
              "COLUMN_NAME" => $ref[1],
              "SQL_FOR_JOIN" => $ref[2]
            ));
          }

          array_push($this->cols_infos, $ligne);
          $current_index++;
        }
      }
    } catch (GenericDaoError $error) { throw $error; }
  }

  /**
   * Destructeur de la classe.
   * 
   * Libère la connexion à la base de données ainsi que les listes d'informations de colonnes et de clés étrangères.
   */
  function __destruct() {
    $this->db_conn->close();
    $this->col_infos = null;
    $this->fk_links = null;
  }
  
  /**
   * Méthode de connexion à la base de données.
   *
   * @throws     GenericDaoError  En cas d'erreur lors de l'établissement de la connexion à la BdD
   *
   * @return     Mysqli           Handler de connexion à la base de données
   */
  protected function connect() {
    $db_conn = new Mysqli(GenericDao::DB_HOST, GenericDao::DB_USER, GenericDao::DB_PASSWD, GenericDao::DB_NAME, GenericDao::DB_PORT);
    $db_conn->set_charset(GenericDao::DB_CHARSET);
    if ($db_conn->connect_errno)
      throw new GenericDaoError(null, $db_conn->connect_errno, $db_conn->connect_error);
    else $this->db_conn = $db_conn;
  }

  /**
   * Méthode permettant d'exécuter une requête SQL
   *
   * @param      string           $sql      Code SQL de la requête
   *
   * @throws     GenericDaoError  En cas d'erreur lors de l'exécution de la requête
   *
   * @return     MysqliResult     Résultat de la requête SQL
   */
  protected function query($sql) {
    if (!$result = $this->db_conn->query($sql))
      throw new GenericDaoError($sql, $this->db_conn->connect_errno, $this->db_conn->connect_error);
    else return $result;
  }
  
  /**
   * Retourne le tableau associatif de $this->fk_links correspondant à l'indice passé en paramètre ou null, le cas échéant
   *
   * @param      int     $cols_infos_index  L'indice correspondant dans $this->cols_infos
   *
   * @return     Array   L'occurrence correspondante dans $this->fk_links ou null, si pas de correspondance
   */
  protected function getFkLink($cols_infos_index) {
    foreach ($this->fk_links as $current_fk_link) {
      if ($current_fk_link["FOR_INDEX"] === $cols_infos_index) {
        return $current_fk_link;
      }
    }
    return null;
  }

  /**
   * Retourne la liste des champs séparés par des virgules, pour la construction d'un SELECT ou d'un INSERT
   * Anticipe l'utilisation de jointures, via $this->fk_links si $use_join est à True
   *
   * @param      boolean  $use_joins       Permet de jointer sur les tables référencés pour obtenir des libellés au lieu 
   *                                       des clés étrangères
   *                                       Utiliser $this->getSelectFromClauseWithJoins() dans la clause FROM, 
   *                                       pour finaliser cette démarche
   *                               
   * @param      boolean  $insert_usecase  Permet de générer la liste brute des propriétés pour les INSERT
   *                                       Si ce paramètre est à True, le paramètre $use_joins est ignoré
   *
   * @return     string   L'expression de la liste des champs pour construire la clause SELECT de la requête
   */
  protected function getSqlFieldsList($use_joins, $insert_usecase) {
    $fields_list = [];
    foreach ($this->cols_infos as $current_index => $current_col_infos) {
      if ($insert_usecase) 
        $cur_col_name = $current_col_infos["COLUMN_NAME"];
      else if ($use_joins && $current_fk_link = $this->getFkLink($current_index))
        $cur_col_name = $current_fk_link["SQL_FOR_JOIN"] . " AS " . $current_col_infos["COLUMN_NAME"];
      else {
        $cur_col_name = $this->table_name . "." . $current_col_infos["COLUMN_NAME"];
        if ($current_col_infos["DATA_TYPE"] == "datetime")
          $cur_col_name = "DATE_FORMAT(" . $cur_col_name . ", '" . $current_col_infos["DATA_PARAMS"] ."') AS " . 
                          $current_col_infos["COLUMN_NAME"];
      }

      array_push($fields_list, $cur_col_name);
    }
    return implode(", ", $fields_list);
  }

  /**
   * Génère l'expression SQL de la clause FROM dans les SELECT, avec les jointures si nécessaire
   *
   * @return     string  L'expression SQL de la clause FROM
   */
  protected function getSelectFromClauseWithJoins() {
    $result = $this->table_name;
    foreach ($this->cols_infos as $current_index => $current_col_infos) {
      if ($current_fk_link = $this->getFkLink($current_index)) {
        $result .= " INNER JOIN " . $current_fk_link["TABLE_NAME"] . " ON " .
                    $this->table_name . "." . $current_col_infos["COLUMN_NAME"] . "=" .
                    $current_fk_link["TABLE_NAME"] . "." . $current_fk_link["COLUMN_NAME"];
      }
    }
    return $result;
  }

  /**
   * Permet d'obtenir le nom de la colonne définie dans le dictionnaire de données comme ID de la table
   *
   * @return     string  Le nom de la colonne servant d'ID, ou null le cas échéant
   */
  protected function getIdColName() {
    foreach ($this->cols_infos as $current_col_infos)
      if ($current_col_infos["IS_ID_COL"])
        return $current_col_infos["COLUMN_NAME"];
    return null;
  }



  //****************************************************//
  // Méthodes d'aide à la construction des requêtes SQL //
  //****************************************************//

  // Méthode anti-injection SQL (encadre la valeur entre '', double ' , et retire les ;)
  public static function getSqlString($str) {
    if ($str == "")
      return "NULL";
    else return "'" . str_replace(";", "", str_replace("'", "''", str_replace("<", "&lt;", str_replace(">", "&gt;", $str)))) . "'";
  }

  // Méthode générant un DATETIME correspondant à l'instant T
  protected static function getSqlNow() {
    $now = new DateTime();
    return "STR_TO_DATE('" . $now->format('d/m/Y H:i:s') . "', '%d/%m/%Y %H:%i:%s')";
  }

  /**
   * Applique au champs, le formattage paramétrée dans DATA_PARAMS en fonction du DATA_TYPE de la colonne.
   *
   * @param      Array   $col_infos  The col infos
   * @param      mixed   $value      La valeur à insérer
   *
   * @return     string  The sql formatted value.
   */
  protected function getSqlFormattedValue($col_infos, $value) {
    $sql_value = $this->getSqlString($value);
    if ($col_infos["DATA_TYPE"] == "datetime")
      $sql_value = "STR_TO_DATE(" . GenericDao::getSqlString($sql_value) . ", '" . $col_infos["DATA_PARAMS"] ."')";
    return $sql_value;
  }



  //****************************************************//
  // Méthodes contruisant et exécutant les requêtes SQL //
  //****************************************************//

  /**
   * Retourne tous les éléments de la table, si aucun filtre n'est passé en paramètre
   * @todo La gestion des filtres est perfectible 
   *   => réfléchir à une classe permettant de gérer des filtres complexes, sans imposer l'utilisation de SQL
   *
   * @param      $sql_filter      Clause WHERE
   *
   * @throws     GenericDaoError  Si erreur dans la requête
   *
   * @return     array  La liste des enregistrements sous la forme de tableaux associatifs
   */
  public function getList($sql_filter) {
    try {
      $sql = "SELECT " . $this->getSqlFieldsList(true, false) . " FROM " . $this->getSelectFromClauseWithJoins() . " " . $sql_filter;
      $query_result = $this->query($sql);
      $result = [];
      while($row = $query_result->fetch_assoc())
        array_push($result, $row);
      return $result;
    } catch (GenericDaoError $error) { throw $error; }
  }

  /**
   * Permet de récupérer un enregistrement spécifique à partir de son ID (colonne déclarée via IS_ID_COL dans le paramétrage de la table)
   * Fournit les données brutes, pour un affichage en formulaire => les valeurs de clés étrangères sont brutes.
   *
   * @param      mixed            $id     Valeur de l'ID pour lequel on souhaite les données
   *
   * @throws     GenericDaoError  Si aucune colonne ID n'est pas paramétrée pour cette table, ou si erreur dans la requête
   *
   * @return     MysqliResult     L'enregistrement correspondant
   */
  public function getById($id) {
    if (!$id_col_name = $this->getIdColName())
      throw new GenericDaoError(null, null, "getById() non utilisable pour la table $this->table_name : colonne ID non définie.");

    try {
      $sql = "SELECT " . $this->getSqlFieldsList(false, false) . " FROM $this->table_name WHERE $id_col_name = $id";
      $query_result = $this->query($sql);
      return $query_result->fetch_assoc();
    } catch (GenericDaoError $error) { throw $error; }
  }

  /**
   * Supprime un/des enregistrement/s dans la table, en fonction du filtre SQL passé en paramètre
   * ATTENTION: en l'absence de filtre, revient à un TRUNCATE TABLE
   * @todo La gestion des filtres est perfectible 
   *   => réfléchir à une classe permettant de gérer des filtres complexes, sans imposer l'utilisation de SQL
   *
   * @param      string  $sql_filter  Clause WHERE
   *
   * @return     int     Nombre d'enregistrements supprimés
   */
  public function deleteAll($sql_filter) {
    try {
      $sql = "DELETE FROM $this->table_name $sql_filter";
      return $this->query($sql);
    } catch (GenericDaoError $error) { throw $error; }
  }

  /**
   * Supprime l'enregistrement de la table, correspondant à l'ID passé en paramètre
   *
   * @param      mixed            $id     ID concerné par la requête en suppression
   *
   * @throws     GenericDaoError  Si colonne ID non paramétrée ou si erreur lors de la requête
   *
   * @return     int              Nombre d'enregistrement supprimés
   */
  public function deleteById($id) {
    if (!$id_col_name = $this->getIdColName())
      throw new GenericDaoError(null, null, "deleteById() non utilisable pour la table $this->table_name : colonne ID non définie.");

    try {
      $sql = "DELETE FROM $this->table_name WHERE $id_col_name = $id";
      return $this->query($sql);
    } catch (GenericDaoError $error) { throw $error; }
  }

  /**
   * Fonction d'aiguillage entre $this->update() et $this->insert()
   *
   * @param      Array            $record  Enregistrement à insérer ou mettre à jour dans la table 
   * 
   * @return     int              ID de l'enregistrement créé ou mis à jour
   *
   * @throws     GenericDaoError  Si l'enregistrement ne référence pas toutes les colonnes de la table, avec ses index
   *                              Si l'insert ou l'update jète une exception
   */
  public function write($record) {
    foreach ($this->cols_infos as $current_col_infos) {
      $col_name = $current_col_infos["COLUMN_NAME"];
      if (!array_key_exists($col_name, $record))
        throw new GenericDaoError(null, null, "Enregistrement non valide: index $col_name absent");
    }

    try {
      $id_col_name = $this->getIdColName();
      if ($id_col_name && isset($record[$id_col_name]))
        return $this->update($record, $id_col_name);
      else return $this->insert($record, $id_col_name);
    } catch (GenericDaoError $error) { throw $error; }
  }

  /**
   * Fonction générant et exécutant un INSERT à partir des valeurs de $record
   *
   * @param      Array            $record       L'enregistrement sous forme de tableau associatif
   * @param      string           $id_col_name  Le nom de la colonne référencée comme ID, dans le paramétrage
   *
   * @throws     GenericDaoError  Si l'INSERT se passe mal
   *
   * @return     int              L'ID généré si $id_col_name est initialisé, sinon le nombre de lignes insérées
   */
  protected function insert($record, $id_col_name) {
    $values_list = [];
    foreach ($this->cols_infos as $current_col_infos) {
      $col_name = $current_col_infos['COLUMN_NAME'];
      $value = $this->getSqlFormattedValue($current_col_infos, $record[$col_name]);
      array_push($values_list, $value);
    }

    try {
      $sql = "INSERT INTO $this->table_name (" . $this->getSqlFieldsList(false, true) . ") VALUES (" . implode(", ", $values_list) . ")";
      $sql_result = $this->query($sql);

      if (isset($id_col_name))
        return $this->db_conn->insert_id;
      else return $sql_result;
    } catch (GenericDaoError $error) { throw $error; }
  }

  /**
   * Génère et exécute un UPDATE en fonction de $record
   *
   * @param      Array            $record       L'enregistrement à mettre à jour, sous la forme d'un tableau associatif
   * @param      string           $id_col_name  Le nom de la colonne définie comme ID de la table
   *
   * @throws     GenericDaoError  Si l'UPDATE se passe mal
   *
   * @return     int              Le nombre de lignes mises à jour
   */
  protected function update($record, $id_col_name) {
    $sets_list = [];
    foreach ($this->cols_infos as $current_col_infos) {
      $col_name = $current_col_infos["COLUMN_NAME"];
      if ($col_name !== $id_col_name) {
        $value = $this->getSqlFormattedValue($current_col_infos, $record[$col_name]);
        array_push($sets_list, $col_name . " = " . $value);
      }
    }

    try {
      $sql = "UPDATE $this->table_name SET " . implode(", ", $sets_list) . 
            " WHERE $id_col_name = " . $this->getSqlString($record[$id_col_name]);
      $this->query($sql);
      return $record[$id_col_name];
    } catch (GenericDaoError $error) { throw $error; }
  }
}

//--------------------------------------------------------------------//
/* Classe d'erreur GenericDaoError, utilisée par la classe GenericDao */
//--------------------------------------------------------------------//
/** @todo Ajouter des codes erreur en constante de la classe, pour identifier plus spécifiquement la cause du problème, en débug */
final class GenericDaoError extends Error {
  
  // Propriétés
  private $query;
  private $errno;
  private $error;

  // Ascesseurs et mutateurs
  public function getQuery()      { return $this->query; }
  public function setQuery($val)  { $this->query = $val; }
  public function getErrno()      { return $this->errno; }
  public function setErrno($val)  { $this->errno = $val; }
  public function getError()      { return $this->error; }
  public function setError($val)  { $this->error = $val; }

  // Constructeur
  function __construct($query, $errno, $error) {
    $this->query = $query;
    $this->errno = $errno;
    $this->error = $error;
  }

  // Fonction d'affichage --> surcharge fonction magique __toString()
  public function __toString() {
    if ($this->query)
      return "Query: $this->query \nCode: $this->errno \nError: $this->error";
    else return 'Échec connexion => ' . $this->errno . ': ' . $this->error; 
  }

  // Fonction permettant les retour d'erreur depuis les APIs, au format JSON
  public function getJson() {
    return json_encode([
      'query' => $this->query,
      'code'  => $this->errno,
      'error' => $this->error
    ]);
  }
}

//--------------------------------------------------------------------//
/* Fonctions de connexion et de requêtes, indépendantes de GenericDao */
//--------------------------------------------------------------------//
/**
 * Connexion à la base de données, sur la base des informations stockées dans la classe GenericDao
 *
 * @throws     GenericDaoError  Erreur de connexion
 *
 * @return     Mysqli           Handler de connexion à la base de données
 */
function db_connect() {
    $db_conn = new Mysqli(GenericDao::DB_HOST, GenericDao::DB_USER, GenericDao::DB_PASSWD, GenericDao::DB_NAME, GenericDao::DB_PORT);
    $db_conn->set_charset(GenericDao::DB_CHARSET);
    if ($db_conn->connect_errno)
      throw new GenericDaoError(null, $db_conn->connect_errno, $db_conn->connect_error);
    else return $db_conn;
}

/**
 * Exécute une requête SQL
 *
 * @param      string           $sql    Le code sql exécuté
 *
 * @throws     GenericDaoError  Erreur de requête
 *
 * @return     MysqliResult     Resultset
 */
function query($sql) {
    try {
        $db_conn = db_connect();
        if (!$result = $db_conn->query($sql))
            throw new GenericDaoError($sql, $db_conn->connect_errno, $db_conn->connect_error);
        else return $result;
        $db_conn->close();
    } catch (GenericDaoError $error) { throw $error; }
}
?>