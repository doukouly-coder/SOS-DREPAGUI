# HEMATO GUI — architecture proposée (à valider avant la phase 4)

Le brief décrit une **plateforme** (comptes, rendez-vous, boutique, espace pro, tableau de bord), pas
seulement un site vitrine. La méthode Claude to WordPress couvre le design et les pages éditables ;
les fonctions applicatives reposent sur des extensions éprouvées, branchées sous le thème sur mesure.

## Ce que porte le thème sur mesure (blocs natifs, 100 % éditable)

| Élément | Réalisation |
|---|---|
| Accueil, Drépanocytose, Hémophilie, Cancers du sang, Hémostase, Diagnostic, Outils, À propos, Contact, Septembre Rouge | Pages WordPress en blocs natifs, sans `core/html` |
| En-tête fixe, menu mobile, pied de page, WhatsApp flottant | Parties de modèle du thème |
| Infographies interactives, onglets (leucémies / lymphomes / myélome / SMD…) | Toutes les variantes présentes dans la page, bascule de classe en JavaScript |
| Actualités et rubrique Septembre Rouge | Articles WordPress + catégories (médicales, recherche, congrès, recommandations, sensibilisation, campagnes, événements) |
| SEO | URL propres (`/drepanocytose/`, `/hemophilie/`, `/cancers-du-sang/`, `/hemostase/`, `/ebooks/`, `/rendez-vous/`, `/actualites/`), titres et descriptions par page, données structurées `MedicalOrganization`, sitemap |

## Ce qui demande des extensions (hors du périmètre de la méthode, à choisir ensemble)

| Besoin | Proposition |
|---|---|
| Prise de rendez-vous en 5 étapes, confirmation, notifications | Extension de réservation (Amelia ou Bookly) : types de consultation, créneaux, confirmation à l'écran, e-mail/SMS, lien WhatsApp |
| Boutique d'eBooks gratuits et payants, téléchargement sécurisé, historique, bibliothèque | WooCommerce, produits virtuels téléchargeables ; « Mon compte › Téléchargements » sert de bibliothèque personnelle |
| Mobile Money (Orange Money, MTN MoMo) | Passerelle WooCommerce d'un agrégateur couvrant la Guinée — à confirmer avec votre banque ou votre opérateur |
| Compte patient (profil, rendez-vous, documents, favoris, notifications) | Comptes WordPress, rôle « patient », espace client de l'extension de réservation + petite extension maison pour les documents privés et les favoris |
| Espace professionnel sécurisé | Rôle « professionnel » validé manuellement (numéro d'ordre), contenus réservés à ce rôle |
| Bibliothèque « Hématologie pratique » avec recherche par maladie, examen, traitement, âge, urgence, spécialité | Type de contenu « fiche » + six taxonomies (extension maison légère) |
| Formations, quiz, certificats, webinaires | Extension LMS (Tutor LMS ou LearnDash) |
| Outils hématologiques | Calculateurs JavaScript dans le thème (NFS, formule leucocytaire, Cockcroft-Gault / CKD-EPI, IPSS-R). IPSS-M : renvoi vers le calculateur officiel plutôt qu'une réimplémentation. Mention « outil d'aide, ne remplace pas le jugement clinique » sur chaque outil |
| Tableau de bord administrateur | wp-admin + widget de statistiques : visiteurs (Matomo ou Site Kit), rendez-vous (extension de réservation), ventes et téléchargements (WooCommerce), inscrits |

## Sécurité et confidentialité

- Double authentification pour les comptes administrateurs et professionnels, limitation des tentatives de connexion, HTTPS partout.
- Aucune donnée médicale personnelle affichée publiquement ; documents patients hors du dossier public `uploads`, servis après contrôle d'accès.
- Journal des actions administratives (Simple History ou WP Activity Log), sauvegardes automatiques hors serveur.
- Collecte minimale au rendez-vous ; mentions légales et politique de confidentialité conformes à la législation guinéenne sur les données personnelles.

## Évolutions prévues

L'API REST de WordPress expose pages, fiches, articles et comptes : elle sert de base aux applications
Android et iOS, aux notifications push, à un chatbot éducatif, à la téléconsultation et à un futur espace laboratoire.
