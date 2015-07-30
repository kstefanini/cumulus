<?php

class StockageDisque {

	/** Config passée par StockageTB.php */
	protected $config;

	/** Chemin de base du répertoire de stockage */
	protected $racine_stockage;

	/** Droits affectés aux nouveaux dossiers */
	protected $droits = 0755;

	protected $ds = DIRECTORY_SEPARATOR;

	public function __construct($config) {
		$this->config = $config;

		$this->racine_stockage = $config['adapters']['StockageTB']['storage_root'];
		// s'assure que le chemin finit par un "/"
		$this->racine_stockage = rtrim($this->racine_stockage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Supprime le fichier $chemin du disque
	 * @param type $chemin
	 * @return type
	 */
	public function supprimerFichier($chemin) {
		return unlink($chemin);
	}

	/**
	 * Copie un fichier temporaire défini par $infosFichier dans le chemin
	 * $cheminDossier du stockage, et le nomme $clef
	 * @param type $infosFichier un tableau partiellement compatible avec $_FILES
	 *		(doit contenir "tmp_name")
	 * @param type $cheminDossier dossier parent, relatif à la racine du stockage
	 * @param type $clef clef ou nom du fichier
	 */
	public function stockerFichier($infosFichier, $cheminDossier, $clef) {
		$cheminDossier = $this->preparerCheminDossier($cheminDossier);
		$clef = $this->desinfecterNomFichier($clef);
		// tantantan taaaaan !!!!
		$destination_finale = $cheminDossier . $clef;

		// déplacement du fichier temporaire
		$origine = $infosFichier['tmp_name'];
		if (! $this->deplacerFichierSurDisque($origine, $destination_finale)) {
			throw new Exception('error while moving file');
		}
		
		// détection du mimetype
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimetype = finfo_file($finfo, $destination_finale);
		finfo_close($finfo);

		return array(
			'disk_path' => $destination_finale,
			'mimetype' => $mimetype
		);
	}

	/**
	 * Déplace le fichier $origine (chemin absolu) vers $destination (chemin
	 * absolu)
	 */
	protected function deplacerFichierSurDisque($origine, $destination) {
		$deplacement = false;
		if(is_uploaded_file($origine)) {
			$deplacement = move_uploaded_file($origine, $destination);
		} else {
			$deplacement = rename($origine, $destination);	
		}
		
		return $deplacement;
	}	

	/**
	 * Normalise et transforme un chemin relatif au stockage en chemin absolu;
	 * crée ce dossier si besoin; retourne le chemin absolu si tout s'est bien
	 * passé, false sinon
	 */
	protected function preparerCheminDossier($dossier_destination) {	
		// normalisation du chemin du dossier parent
		$dossier_destination = $this->desinfecterCheminDossier($dossier_destination);				
		$chemin_dossier_complet = $this->racine_stockage . $dossier_destination;
		//echo "DD 3 : $chemin_dossier_complet\n";

		// création du dossier parent si besoin
		if(!is_dir($chemin_dossier_complet)) {
			$ok = mkdir($chemin_dossier_complet, $this->droits, true);
			$chemin_dossier_complet = $ok ? $chemin_dossier_complet : false;		
		}

		return $chemin_dossier_complet;
	}

	/**
	 * Supprime les motifs potentiellements dangereux dans un chemin de dossier,
	 * comme ".." ou plusieurs "/" à la suite; s'assure que 
	 */
	protected function desinfecterCheminDossier($chemin) {
		// pour le moment on supprime les occurences de .. dans les dossiers et les // ou /// etc...
		$chemin = preg_replace("([\.]{2,})", '', $chemin);
		$ds = preg_quote(DIRECTORY_SEPARATOR, '#'); // censé être cross-OS
		$chemin = preg_replace("#($ds+)#", '/', $chemin);
		// retire le séparateur de dossier de gauche s'il est présent et s'assure que celui de droite existe
		$chemin = trim($chemin, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		
		return $chemin;
	}

	/**
	 * Supprime les motifs potentiellements dangereux dans un nom de fichier,
	 * comme ".." ou les caractères chelous @TODO vérifier cette définition !
	 * http://stackoverflow.com/questions/2021624/string-sanitizer-for-filename
	 */
	protected function desinfecterNomFichier($nom) {
		// Remove anything which isn't a word, whitespace, number
		// or any of the following caracters -_~,;:[]().
		$nom = preg_replace("([^\w\s\d\-_~,;:\[\]\(\).])", '', $nom);
		// Remove any runs of periods (thanks falstro!)
		$nom = preg_replace("([\.]{2,})", '', $nom);

		return $nom;
	}
}