/**
 * Classe permettant de gérer l'interface utilisateur
 *
 * @class      Controller (name)
 */
class Controller {
	contructor() {
		this.scope = {};
	}

	init() {
		xhr("note.getList.php", "POST", {}, (response)=> {
			let listContainer = document.getElementById("notes-container");

			if (response.length) {
				listContainer.innerHTML = "";
				this.addListLine({Titre:"Titre", Contenu:"Contenu"}, listContainer, true);

				for (let elem of response)
					this.addListLine(elem, listContainer);
			} else {
				listContainer.innerHTML = "Aucune note enregistrée";
			}

		});
	}

	addListLine(elem, listContainer, isEntete) {
		let ligne = document.createElement("DIV");
		ligne.classList.add("list-line");

		if (isEntete)
			ligne.classList.add("list-headers");
		
		let caseTitre = document.createElement("DIV");
		caseTitre.innerHTML = elem.Titre;
		caseTitre.style.width = "30%";
		let caseContenu = document.createElement("DIV");
		caseContenu.innerHTML = elem.Contenu;
		caseContenu.style.width = "65%";

		ligne.appendChild(caseTitre);
		ligne.appendChild(caseContenu);
		
		if (!isEntete) {
			let btnSuppr = document.createElement("BUTTON");
			btnSuppr.innerHTML = "x";
			btnSuppr.style.width = "5%";
			btnSuppr.addEventListener('click', ()=> {
				xhr("note.delete.php", "POST", elem, (response)=> {
					ligne.remove();
				});
			});
			ligne.appendChild(btnSuppr);			
		}

		listContainer.appendChild(ligne);
	}

	createNote() {
		let titre_elt = document.getElementById("titre"),
			contenu_elt = document.getElementById("contenu");
		let obj = {
			IdNote: null,
			Titre: titre_elt.value,
			Contenu: contenu_elt.value
		}
		xhr("note.write.php", "POST", obj, (response)=> {
			obj.IdNote = response.IdNote;
			this.addListLine(obj, document.getElementById("notes-container"));
			titre_elt.value = "";
			contenu_elt.value = "";
		});
	}
}