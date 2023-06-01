/***** Fonctions AJaX *****/

/****************************************************************************
 * Fonction de routage pour l'affichage dans la partie centrale de la page. *
 ****************************************************************************
 * @param | {String}   | url      | URL de la ressource HTML                *
 * @param | {String}   | target   | HTMLElement de destination du routage   *
 * @param | {function} | callback | Fonction de rappel optionnelle          *
 ****************************************************************************/
function routage(url, target, callback) {
  xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4) {
      if (this.status == 200) {
        target.innerHTML = this.responseText;
        if (callback)
          callback();
      }
      if (this.status == 404) { target.innerHTML = "Page not found."; }
    }
  }
  xhttp.open("GET", url, true);
  xhttp.send();
}

/**********************************************************************************
 * Fonction générique d'appel AJaX                                                *
 **********************************************************************************
 * @param  | {String}        | url    | URL de l'API appelée                      *
 * @param  | {String}        | method | Méthode de passage des paramètres         *
 * @param  | {String|Object} | params | Paramètres (string en GET, objet en POST) *
 * @param  | {Function}      | fnOk   | Fonction de rappel si succès              *
 **********************************************************************************/
function xhr(url, method, params, fnOk) {
  let xhttp = new XMLHttpRequest();
  xhttp.responseType = 'json';
  xhttp.onload = function () { 
    fnOk(this.response);
  }
  
  // On paramètre la requête différemment suivant la méthode de transport des paramètres
  if (method === "GET") {
    if (params) params = "?" + params;
    xhttp.open(method, url + params, true);
    xhttp.send();
  } else if (method === "POST") {
    xhttp.open(method, url);
    xhttp.setRequestHeader('Content-Type', 'application/json');
    xhttp.send(JSON.stringify(params));
  } else console.error(`xhr() ===> Type de requête inconnu: '${method}'`);
}

/***** Divers *****/

/****************************************************************************
 * Fonction permettant de récupérer le code Data URL d'un fichier           *
 * (pour en extraire la Base64, s'il s'agit d'une image par exemple)        *
 ****************************************************************************
 * @param  | {HTMLInputElement} | fileFieldId | Champ de type file          *
 * @return | {Promise}          | Le code Data URL du fichier pointé, si ok *
 ****************************************************************************/
const getBase64 = fileField => new Promise((resolve, reject) => {
  const reader = new FileReader();
  let file = fileField.files[0];
  reader.readAsDataURL(file);
  reader.onload = () => resolve(reader.result);
  reader.onerror = error => reject(error);
});

/**
 * Affichage d'un message sous forme de toast
 *
 * @param      string  message  Le message à afficher, au format HTML
 * @param      string  type     Le type de message (info|warn|err) => modifie l'affichage
 * @param      number  duree    The durée d'affichage en millisecondes
 */
function showToastMessage(message, type, duree) {
  let eltToast = document.getElementById("rs-toast");
  eltToast.innerHTML = message;
  eltToast.classList.add(type);
  eltToast.classList.add("shown");
  setTimeout(function() { 
    eltToast.classList.remove("shown");
    setTimeout(function() {
      eltToast.classList.remove(type);
      eltToast.innerHTML = "";
    }, 500);
  }, duree);
}