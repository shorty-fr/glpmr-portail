<?php

namespace Glpmr\PeripheriqueBundle\Controller;

use Glpmr\AuthentificationBundle\Controller\AuthentificationController;
use Glpmr\AuthentificationBundle\Entity\AuthentificationLDAP;
use Glpmr\AuthentificationBundle\Entity\CustomError;
use Glpmr\PeripheriqueBundle\Entity\Peripherique;
use Glpmr\PeripheriqueBundle\Entity\PeripheriqueDAO;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class PeripheriqueController extends Controller
{

    public function listerAction()
    {
        AuthentificationController::isConnected();

        $dao = new PeripheriqueDAO($this->getDoctrine()->getConnection());
        $peripheriques = $dao->listerUser();
        $session = new Session();

        $ips = $dao->getIps($session->get('username'));
        return $this->render("GlpmrPeripheriqueBundle:Default:manage_mac_addr.html.twig", array("peripheriques" => $peripheriques, "tableau_ip" => $ips));
    }

    public function ajouterAction(Request $request)
    {
        AuthentificationController::isConnected();
        try {
            // V�rification de l'unicit� de l'adresse mac
            $dao = new PeripheriqueDAO($this->getDoctrine()->getConnection());
            $add = $request->get("adresse");
            $count = $dao->countMac($add);
            if ($count == 0) {
                $obj = new Peripherique();
                $obj->setHostname($request->get("hostname"));
                $obj->setAdd_mac($add);
                $obj->setType($request->get("type"));
                $obj->setDescription($request->get("description"));

                // Calcul Automatique de l'IP
                $session = new Session();
                $obj->setAdd_ip($obj->ConstruireIP($session->get("promotion"), $request->get("ip"), $this->getDoctrine()->getConnection()));

                $session = new Session();
                $obj->setProprietaire($session->get("username"));
                $obj->setProprietaire_classe($session->get("promotion"));
                $dao = new PeripheriqueDAO($this->getDoctrine()->getConnection());
                $dao->ajouter($obj);

                $title = "Success!";
                $message = "Le peripherique a bien ete enregistre";

                $dao->exportToJson();

            } else {
                $title = "Erreur!";
                $message = "Erreur, l'adresse MAC est déjà renseignée";
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        return $this->render("GlpmrPeripheriqueBundle:Default:ajouter_peripherique.html.twig", array("title" => $title, "message" => $message));

    }

    public function supprimerAction(Request $request)
    {
        AuthentificationController::isConnected();
        try {
            $id = $request->get("id");
            $obj = new Peripherique();
            $obj->setId($id);
            $session = new Session();
            $dao = new PeripheriqueDAO($this->getDoctrine()->getConnection());

            //test anti cheat
            if (!$session->get("admin")) {
                $cheat = $dao->anticheat($session->get('username'), $obj);
                if ($cheat) {
                    CustomError::showMessage("Vous n'avez pas les droits (noob!) 403");
                    return $this->redirect($this->generateUrl("glpmr_peripherique_gestion"));
                }
            }

            $dao->supprimer($obj);

            $dao->exportToJson();

            $title = "Success!";
            $message = "Le peripherique a bien ete supprime!";
        } catch (Exception $e) {
            $title = "Erreur!";
            $message = "Erreur lors de la suppression du peripherique";
        }

        return $this->render("GlpmrPeripheriqueBundle:Default:supprimer_peripherique.html.twig", array("title" => $title, "message" => $message));
    }

    public function modifierAction(Request $request)
    {
        AuthentificationController::isConnected();
        if ($this->get('request')->getMethod() == "POST") {
            $session = new Session();
            $dao = new PeripheriqueDAO($this->getDoctrine()->getConnection());

            $obj = new Peripherique();
            $obj->setId($request->get("id"));

            //test anti cheat
            if (!$session->get("admin")) {
                $cheat = $dao->anticheat($session->get('username'), $obj);
                if ($cheat) {
                    CustomError::showMessage("Vous n'avez pas les droits (noob!) 403");
                    return $this->redirect($this->generateUrl("glpmr_peripherique_gestion"));
                }
            }

            $dao->supprimer($obj);


            $obj->setHostname($request->get("hostname"));

            // Calcul Automatique de l'IP
            $session = new Session();
            $obj->setAdd_ip($obj->ConstruireIP($session->get("promotion"), $request->get("ip"), $this->getDoctrine()->getConnection()));

            $obj->setAdd_mac($request->get("adresse"));
            $obj->setType($request->get("type"));
            $obj->setDescription($request->get("description"));
            $obj->setProprietaire($session->get("username"));
            $obj->setProprietaire_classe($session->get("promotion"));

            $dao->ajouter($obj);

            $dao->exportToJson();

            $title = "Success!";
            $message = "Le peripherique a bien ete modifie!";


            return $this->render("GlpmrPeripheriqueBundle:Default:modifier_valider_peripherique.html.twig", array("title" => $title, "message" => $message));

        } else {
            try {
                $id = $request->query->get('id');
                $peripherique = new Peripherique();
                $peripherique->setId($id);
                $dao = new PeripheriqueDAO($this->getDoctrine()->getConnection());
                $obj = $dao->listerOne($peripherique);
                $session = new Session();
                $ips = $dao->getIps($session->get('username'));

                //test anti cheat
                if (!$session->get("admin")) {
                    $p = new Peripherique();
                    $p->setId($obj['id']);
                    $cheat = $dao->anticheat($session->get('username'), $p);
                    if ($cheat) {
                        CustomError::showMessage("Vous n'avez pas les droits (noob!) 403");
                        return $this->redirect($this->generateUrl("glpmr_peripherique_gestion"));
                    }
                }
                return $this->render("@GlpmrPeripherique/Default/modifier_peripherique.html.twig", array("peripherique" => $obj, "tableau_ip" => $ips));

            } catch (Exception $e) {

            }
        }
    }


    public function rechercheAction()
    {
        $session = new Session();

        if (!$session->get("admin")) {
            CustomError::showMessage("Vous n'avez pas les droits");
            return $this->redirect($this->generateUrl("glpmr_peripherique_gestion"));
        } else {
            AuthentificationController::isConnected();
            return $this->render('@GlpmrPeripherique/Default/recherche.html.twig');
        }
    }

    public function resultatAction(Request $request)
    {
        AuthentificationController::isConnected();
        $username = $request->get('username');
        $promotion = $request->get('promotion');
        $add_ip = $request->get('add_ip');
        $add_mac = $request->get('add_mac');

        $dao = new PeripheriqueDAO($this->getDoctrine()->getConnection());
        $peripheriques = $dao->rechercheAdmin($username, $promotion, $add_ip, $add_mac);

        return $this->render("GlpmrPeripheriqueBundle:Default:resultats.html.twig", array("peripheriques" => $peripheriques));
    }

    public function supprimerGroupeAction()
    {
        $session = new Session();

        if (!$session->get("admin")) {
            CustomError::showMessage("Vous n'avez pas les droits");
            return $this->redirect($this->generateUrl("glpmr_peripherique_gestion"));
        } else {
            AuthentificationController::isConnected();
            return $this->render("@GlpmrPeripherique/Default/admin_suppr_groupe.html.twig");
        }
    }

    public function supprimerGroupeValiderAction(Request $request)
    {
        AuthentificationController::isConnected();
        try {
            $promotion = $request->get('promotion');
            $dao = new PeripheriqueDAO($this->getDoctrine()->getConnection());
            $dao->supprimerGroupe($promotion);

            $dao->exportToJson();

            $title = "Success!";
            $message = "Les peripheriques de la classe " . $promotion . " ont bien ete supprimes!";
        } catch (Exception $e) {
            $title = "Erreur!";
            $message = "Erreur lors de la suppression des peripheriques";
        }

        return $this->render("GlpmrPeripheriqueBundle:Default:supprimer_peripherique.html.twig", array("title" => $title, "message" => $message));
    }

    // Route de test
    public function testAction()
    {
        $dao = new PeripheriqueDAO($this->getDoctrine()->getConnection());
        $session = new Session();


    }
}
