# CHANGELOG NSADDEINVOICING FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0.2 - 25/11/2026
- Commentaires du code traduits en français (PHP, CSS et fichiers de langue)
- Réglages : option « Forcer le débordement de la liste déroulante hors de la popup » (désactivée par défaut) : dans la fenêtre « Changer la référence », la liste des produits s'affiche au-dessus de la popup au lieu d'y être coupée (affichage sous macOS)
- Réglages : option « Forcer la hauteur de la popup à la hauteur de la liste déroulante standard » (désactivée par défaut) : la fenêtre « Changer la référence » est agrandie vers le bas pour que la liste des produits y tienne sans être coupée (affichage sous macOS)

## 1.0.1 - 21/11/2026
- Traductions allemande, espagnole et italienne : les libellés des actions sur les lignes (« Actions sur les lignes », « Changer la référence », « Supprimer les lignes », fenêtre et messages) s'affichaient en français, un bloc français en double les masquait ; il est retiré
- README : durée de garantie (accès aux mises à jour) permanente

## 1.0.0 - 20/11/2026
- Version initiale : module NS INFO (famille NS INFO, réglages via nsinfoSetup), cinq langues (fr, en, de, es, it), logo boule NS INFO avec l'image du module e-invoicing
- Factures fournisseur en brouillon : case à cocher sur chaque ligne et liste d'actions avec pictos Font Awesome 7 : « Changer la référence » (référence en cours, nouvelle référence choisie parmi les produits du fournisseur de la facture ; produit et référence fournisseur de la ligne mis à jour) et « Supprimer les lignes » (totaux recalculés)
- Compatible avec nsdtools : c'est nsdtools qui laisse la place à ce module pour les actions de masse sur les lignes des factures fournisseur en brouillon (rien à bloquer ici)
