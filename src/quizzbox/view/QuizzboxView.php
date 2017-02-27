<?php
namespace quizzbox\view;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class quizzboxview
{
	protected $data = null ;
	protected $baseURL = null;

    public function __construct($data)
	{
        $this->data = $data;
    }

	private function getStatus() {
		if(array_key_exists('status', $this->data)) {
			if(is_numeric($this->data['status'])) {
				$status = $this->data['status'];
				unset($this->data['status']);
				return $status;
			}
		}
		return 400;
	}

	private function menu($req, $resp, $args)
	{
		$html = "
			<li>
				<a href='".$this->baseURL."'>Accueil</a>
			</li>
			<li>
				<a href='".$this->baseURL."/uploadQuizz'>Installer un quizz depuis un fichier</a>
			</li>
		";
		
		// Vérifier l'état de la connexion Internet
		$connected = @fsockopen("www.example.com", 80); 
		if($connected)
		{
			// Connecté
			fclose($connected);
			
			$html .= "
			<li>
				<a href='".$this->baseURL."/network'>Télécharger des quizz</a>
			</li>
			";
		}
		else
		{
			// Pas connecté
		}
		
		// Vérifier l'authentification en tant qu'administrateur
		if(!(isset($_SESSION["admin"])))
		{
			$html .= "
			<li>
				<a href='".$this->baseURL."/admin'>Administration</a>
			</li>
			";
		}
		else
		{
			$html .= "
			<li>
				<a href='".$this->baseURL."/admin'>Déconnexion</a>
			</li>
			";
		}
		
		return $html;
	}
	
	private function header($req, $resp, $args)
	{
		$html = "
			<!DOCTYPE html>
			<html lang='fr'>
				<head>
					<meta charset='UTF-8'>
					<meta name='viewport' content='width=device-width, initial-scale=1'>
					<title>Quizzbox</title>
					<script src='jquery.min.js'></script>
					<script src='script.js'></script>
					<link rel='stylesheet' type='text/css' href='css/style.css'/>
				</head>
				<body>
					<header>
						<h1>
							Quizzbox
						</h1>
					</header>
					<ul id='menu'>
						".$this->menu($req, $resp, $args)."
					</ul>
					<form id='recherche' name='recherche' method='GET' action='".$this->baseURL."/recherche'>
						<input type='text' name='q' id='rechercheText' placeholder='Rechercher un quizz installé...'
		";

		if(isset($_GET["q"]))
		{
			if($_GET["q"] != "")
			{
				$html .= "value='".filter_var($_GET["q"], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."'";
			}
		}

		$html .= " required />
						<button type='submit' id='actionRecherche'>OK</button>
					</form>

		";
		
		if(isset($_SESSION["message"]))
		{
			$html .= "<div id='message'>".filter_var($_SESSION["message"], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."</div>";
			unset($_SESSION["message"]);
		}
		$html .= "
					
					<div id='content'>
		";
		
		return $html;
	}
	
	private function footer($req, $resp, $args)
	{
		$html = "
					</div>
					<footer>
						Quizzbox
					</footer>
				</body>
			</html>
		";
		
		return $html;
	}
	
	
	// -----------
	
	
	private function calculDifficulteQuizz($quizz)
	{
		// Un coefficient d'une question peut avoir comme valeur : 1, 2, 3, 4 ou 5
		// Plus le coefficient est élevé, plus la question est difficile.

		$questions = \quizzbox\model\question::where('id_quizz', $quizz->id)->get();

		$cumulCoefficients = 0;
		foreach($questions as $uneQuestion)
		{
			$cumulCoefficients = $cumulCoefficients + $uneQuestion->coefficient;
		}

		$moyenneDifficulte = 0;
		if(\quizzbox\model\question::where('id_quizz', $quizz->id)->count() != 0)
		{
			$moyenneDifficulte = $cumulCoefficients / (\quizzbox\model\question::where('id_quizz', $quizz->id)->count());
		}

		$difficulte = "Facile"; // moyenneDifficulte < 2
		if($moyenneDifficulte >= 2)
		{
			if($moyenneDifficulte >= 3)
			{
				if($moyenneDifficulte >= 4)
				{
					$difficulte = "Très difficile";
				}
				else
				{
					$difficulte = "Difficile";
				}
			}
			else
			{
				$difficulte = "Moyen";
			}
		}

		return $difficulte;
	}
	
    /*private function afficherCategories($req, $resp, $args)
	{
		$html = "<ul class='elements'>";
		foreach($this->data as $uneCategorie)
		{
			$html .= "
				<li class='block'>
					<h1>
						".$uneCategorie->nom."
					</h1>
					<p>
						<b>Description : </b>
						".$uneCategorie->description."
					</p>
					<p>
						<b>Nombre de quizz : </b>
						".\quizzbox\model\quizz::where('id_categorie', $uneCategorie->id)->count()."
					</p>
					<a class='button' href='".$this->baseURL."/categories/".$uneCategorie->id."'>
						Consulter les quizz
					</a>
				</li>
			";
		}
		$html .= "</ul>";

		return $html;
	}*/

	private function afficherQuizz($req, $resp, $args)
	{
		$html = "<p>".count($this->data)." quizz trouvé(s)</p>";
		$html .= "<ul class='elements'>";
		foreach($this->data as $unQuizz)
		{
			$html .= "
				<li class='block'>
					<h1>
						".$unQuizz->nom."
					</h1>
					<p>
						<b>Détails :</b>
						<ul>
							<li>
								<b>Nombre de questions : </b>
								".\quizzbox\model\question::where('id_quizz', $unQuizz->id)->count()."
							</li>
							<li>
								<b>Difficulté évaluée : </b>
								".$this->calculDifficulteQuizz($unQuizz)."
							</li>
							<li>
								<form method='get' action='".$this->baseURL."/jouer/".$unQuizz->id."'>
									<button type='submit'>Jouer au quizz</button>
								</form>
							</li>
							";

			if(isset($_SESSION["admin"]))
			{
				$html .= "
						<li>
							<form method='post' action='".$this->baseURL."/quizz/".$unQuizz->id."/supprimer'>
								<button type='submit'>Supprimer le quizz</button>
							</form>
						</li>
				";
			}

			$html .= "
						</ul>
					</p>
					<h2>Classement des 10 meilleurs joueurs en local :</h2>
					<table class='classement'>
						<tr>
							<th>Position</td>
							<th>Joueur</td>
							<th>Score</td>
						</tr>
						";

			$scores = \quizzbox\model\quizz::find($unQuizz->id)->scores()->orderBy('score', 'DESC')->take(10)->get();
			$position = 1;
			foreach($scores as $unScore)
			{
				$html .= "
					<tr>
						<td>".$position."</td>
						<td>".\quizzbox\model\joueur::find($unScore->pivot->id_joueur)->first()->pseudo."</td>
						<td>".$unScore->pivot->score."</td>
					</tr>
				";
				$position++;
			}


			$html .= "
					</table></li>
			";
		}
		$html .= "</ul>";

		return $html;
	}
	
	private function connexionFormAdmin($req, $resp, $args) {
		$html = <<<EOT
	<form method="post" action="{$this->baseURL}/admin">
			<p><label for="mdp">Mot de passe d'administration :</label> <input type="password" name="mdp" maxlength="255" required/></p>
			<p><input type="submit" value="S'authentifier" /></p>
		</form>
EOT;
		return $html;
	}
	
	private function rechercher($req, $resp, $args)
	{
		$html = $this->afficherQuizz($req, $resp, $args);

		return $html;
	}
	
	private function networkCategories($req, $resp, $args)
	{
		$html = "<ul class='elements'>";
		foreach($this->data as $uneCategorie)
		{
			$html .= "
				<li class='block'>
					<h1>
						".$uneCategorie->nom."
					</h1>
					<p>
						<b>Description : </b>
						".$uneCategorie->description."
					</p>
					<p>
						<b>Nombre de quizz : </b>
						".\quizzbox\model\quizz::where('id_categorie', $uneCategorie->id)->count()."
					</p>
					<a class='button' href='".$this->baseURL."/network/categories/".$uneCategorie->id."'>
						Consulter les quizz
					</a>
				</li>
			";
		}
		$html .= "</ul>";

		return $html;
	}
	
	private function networkQuizz($req, $resp, $args)
	{
		$url = parse_ini_file($req->getUri()->getBasePath()."/conf/network.ini");
		
		$html = "<p>".count($this->data)." quizz trouvé(s)</p>";
		$html .= "<ul class='elements'>";
		foreach($this->data as $unQuizz)
		{
			$html .= "
				<li class='block'>
					<h1>
						".$unQuizz->nom."
					</h1>
					<p>
						<b>Détails :</b>
						<ul>
							<li>
								<b>Nombre de questions : </b>
								".file_get_contents($url["url"].'/quizz/'.$unQuizz->id.'/nbQuestions', FILE_USE_INCLUDE_PATH)."
							</li>
							<li>
								<b>Difficulté évaluée : </b>
								".$this->calculDifficulteQuizz($unQuizz)."
							</li>
							<li>
								<form method='post' action='".$this->baseURL."/network/quizz/{id}/".$unQuizz->token."/install'>
									<button type='submit'>Installer le quizz</button>
								</form>
							</li>
						</ul>
					</p>
				</li>
			";
		}
		$html .= "</ul>";

		return $html;
	}
	
	private function formUploadQuizz($req, $resp, $args)
	{
		$html = "
			<form method='post' action='".$this->baseURL."/uploadQuizz' enctype='multipart/form-data'>
				<label for='quizz'>Importer votre fichier de quizz :</label> 
				<input type='file' name='quizz' id='quizz' required />
				<button type='submit' name='uploader'>Installer le quizz</button>
			</form>
		";

		return $html;
	}
	
	public function envoiScore($req, $resp, $args)
	{
		$json = "";

		if(is_array($this->data))
		{
			$json = json_encode($this->data);
			$resp = $resp->withHeader('Content-Type', 'application/json');
		}
		else
		{
			$json = $this->data;
			$resp = $resp->withStatus(200)->withHeader('Content-Type', 'application/json');
		}

		$resp->withHeader('Access-Control-Allow-Origin', '*')->getBody()->write($json);
		return $resp;
	}
	
	
	// -----------
	

	public function render($selector, $req, $resp, $args)
	{
		$this->baseURL = $req->getUri()->getBasePath();
		
		$html = $this->header($req, $resp, $args);
		
		// Sélectionne automatiquement le sélecteur.
		$html .= $this->$selector($req, $resp, $args);
		
		$html .= $this->footer($req, $resp, $args);
		
		$resp->getBody()->write($html);
		return $resp;
	}
}
