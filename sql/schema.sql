-- =====================================================
-- NO LABEL - Schema de la base de donnees
-- Projet d'ete ESIG - Ardon Dinaj
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS ligne_commande;
DROP TABLE IF EXISTS commande;
DROP TABLE IF EXISTS stock;
DROP TABLE IF EXISTS produit;
DROP TABLE IF EXISTS taille;
DROP TABLE IF EXISTS categorie;
DROP TABLE IF EXISTS utilisateur;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 1. UTILISATEUR
-- =====================================================
CREATE TABLE utilisateur (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(180)  NOT NULL,
    mot_de_passe    VARCHAR(255)  NOT NULL,   -- hash bcrypt, jamais en clair
    nom             VARCHAR(80)   NOT NULL,
    prenom          VARCHAR(80)   NOT NULL,
    telephone       VARCHAR(30)   DEFAULT NULL,
    role            ENUM('client','admin') NOT NULL DEFAULT 'client',
    actif           TINYINT(1)    NOT NULL DEFAULT 1,   -- suppression logique
    date_inscription DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_utilisateur_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. CATEGORIE
-- =====================================================
CREATE TABLE categorie (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom    VARCHAR(80) NOT NULL,
    slug   VARCHAR(80) NOT NULL,
    UNIQUE KEY uk_categorie_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. TAILLE
-- =====================================================
CREATE TABLE taille (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code   VARCHAR(10) NOT NULL,
    ordre  TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- pour trier S < M < L < XL
    UNIQUE KEY uk_taille_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. PRODUIT
-- =====================================================
CREATE TABLE produit (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categorie_id INT UNSIGNED  NOT NULL,
    nom          VARCHAR(150)  NOT NULL,
    description  TEXT          DEFAULT NULL,
    prix         DECIMAL(8,2)  NOT NULL,
    image        VARCHAR(255)  DEFAULT NULL,   -- nom du fichier dans /public
    actif        TINYINT(1)    NOT NULL DEFAULT 1,   -- suppression logique
    date_ajout   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produit_categorie
        FOREIGN KEY (categorie_id) REFERENCES categorie(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    KEY idx_produit_categorie (categorie_id),
    KEY idx_produit_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. STOCK  --  CLASSE D'ASSOCIATION produit <-> taille
--    La quantite n'appartient ni au produit seul,
--    ni a la taille seule : elle nait de leur croisement.
-- =====================================================
CREATE TABLE stock (
    produit_id  INT UNSIGNED NOT NULL,
    taille_id   INT UNSIGNED NOT NULL,
    quantite    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (produit_id, taille_id),
    CONSTRAINT fk_stock_produit
        FOREIGN KEY (produit_id) REFERENCES produit(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stock_taille
        FOREIGN KEY (taille_id) REFERENCES taille(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. COMMANDE
-- =====================================================
CREATE TABLE commande (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT UNSIGNED NOT NULL,
    numero          VARCHAR(20)  NOT NULL,      -- ex : NL-2026-0001
    date_commande   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut          ENUM('en_attente_paiement','payee','expediee','livree','annulee')
                    NOT NULL DEFAULT 'en_attente_paiement',
    mode_retrait    ENUM('livraison','retrait') NOT NULL DEFAULT 'livraison',
    nom_livraison    VARCHAR(80)  NOT NULL,
    prenom_livraison VARCHAR(80)  NOT NULL,
    adresse         VARCHAR(255) DEFAULT NULL,
    code_postal     VARCHAR(10)  DEFAULT NULL,
    ville           VARCHAR(100) DEFAULT NULL,
    telephone       VARCHAR(30)  NOT NULL,
    commentaire     TEXT         DEFAULT NULL,
    total           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_commande_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uk_commande_numero (numero),
    KEY idx_commande_utilisateur (utilisateur_id),
    KEY idx_commande_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. LIGNE_COMMANDE -- CLASSE D'ASSOCIATION
--    commande <-> produit <-> taille
--    Porte quantite ET prix_unitaire : le prix paye
--    est fige, meme si le tarif du produit change ensuite.
-- =====================================================
CREATE TABLE ligne_commande (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id    INT UNSIGNED NOT NULL,
    produit_id     INT UNSIGNED NOT NULL,
    taille_id      INT UNSIGNED NOT NULL,
    quantite       INT UNSIGNED NOT NULL,
    prix_unitaire  DECIMAL(8,2) NOT NULL,
    CONSTRAINT fk_ligne_commande
        FOREIGN KEY (commande_id) REFERENCES commande(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ligne_produit
        FOREIGN KEY (produit_id) REFERENCES produit(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ligne_taille
        FOREIGN KEY (taille_id) REFERENCES taille(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uk_ligne (commande_id, produit_id, taille_id),
    KEY idx_ligne_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- DONNEES DE TEST
-- =====================================================

INSERT INTO categorie (nom, slug) VALUES
('T-shirts',    't-shirts'),
('Sweats',      'sweats'),
('Vestes',      'vestes'),
('Pantalons',   'pantalons'),
('Accessoires', 'accessoires');

INSERT INTO taille (code, ordre) VALUES
('XS', 1), ('S', 2), ('M', 3), ('L', 4), ('XL', 5), ('XXL', 6), ('Unique', 7);

INSERT INTO produit (categorie_id, nom, description, prix, image, actif) VALUES
(1, 'T-shirt Blank Noir',      'T-shirt coupe oversize en coton biologique 220g. Serigraphie discrete au dos.', 49.00,  't-shirt-blank-noir.jpg', 1),
(1, 'T-shirt Blank Ecru',      'La meme coupe oversize, teinte ecru naturelle non blanchie.',                   49.00,  't-shirt-blank-ecru.jpg', 1),
(1, 'T-shirt Signature',       'Coton epais 240g, logo brode sur la poitrine.',                                 59.00,  't-shirt-signature.jpg',  1),
(2, 'Hoodie Heavyweight Noir', 'Sweat a capuche 450g, doublure brossee, coupe droite.',                         119.00, 'hoodie-noir.jpg',        1),
(2, 'Hoodie Heavyweight Gris', 'Meme construction 450g, gris chine.',                                           119.00, 'hoodie-gris.jpg',        1),
(2, 'Crewneck Essentiel',      'Sweat col rond 380g, finitions cotelees.',                                       99.00, 'crewneck.jpg',           1),
(3, 'Veste Coach Noire',       'Veste coach impermeable, doublure filet, pressions metal.',                     159.00, 'veste-coach.jpg',        1),
(3, 'Bomber Atelier',          'Bomber matelasse, edition limitee a 50 pieces.',                                229.00, 'bomber.jpg',             1),
(4, 'Pantalon Cargo',          'Cargo ample en ripstop, taille elastiquee, six poches.',                        129.00, 'cargo.jpg',              1),
(4, 'Jogging Heavyweight',     'Bas de jogging 450g assorti au hoodie.',                                         99.00, 'jogging.jpg',            1),
(5, 'Casquette Logo',          'Casquette 6 panneaux, logo brode, fermeture ajustable.',                         39.00, 'casquette.jpg',          1),
(5, 'Tote Bag Canvas',         'Sac en toile epaisse 340g, anses renforcees.',                                   29.00, 'tote-bag.jpg',           1);

-- Stock : vetements en XS a XXL, accessoires en taille unique
INSERT INTO stock (produit_id, taille_id, quantite) VALUES
(1,1,4),(1,2,12),(1,3,18),(1,4,15),(1,5,7),(1,6,3),
(2,1,2),(2,2,9),(2,3,14),(2,4,11),(2,5,5),(2,6,0),
(3,2,6),(3,3,10),(3,4,8),(3,5,4),
(4,2,5),(4,3,9),(4,4,12),(4,5,6),(4,6,2),
(5,2,3),(5,3,7),(5,4,9),(5,5,4),
(6,2,8),(6,3,11),(6,4,10),(6,5,5),
(7,3,4),(7,4,6),(7,5,3),
(8,3,2),(8,4,3),(8,5,1),
(9,2,5),(9,3,8),(9,4,7),(9,5,4),
(10,2,6),(10,3,10),(10,4,9),(10,5,5),
(11,7,25),
(12,7,40);


-- Note : les commandes de test sont generees par un script PHP
-- (transaction + hachage des mots de passe), non reproductible en SQL pur.
-- Identifiants de test : sophie.martin@example.ch / Client2026