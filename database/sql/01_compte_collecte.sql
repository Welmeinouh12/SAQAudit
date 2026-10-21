/*
    SQLAudit : création du compte de collecte en LECTURE SEULE
    -----------------------------------------------------------
    À exécuter sur CHAQUE instance auditée, connecté avec un compte sysadmin
    (SSMS ou : sqlcmd -S 127.0.0.1,1433 -E -i 01_compte_collecte.sql).

    Le compte peut LIRE les métadonnées (logins, rôles, permissions, configuration,
    utilisateurs de chaque base) mais ne peut RIEN modifier et ne lit pas les données
    des tables métier.

    >>> Remplacez le mot de passe ci-dessous avant exécution, puis saisissez-le
    >>> dans SQLAudit (page Instances, champ « Mot de passe »).
*/
USE master;
GO

IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'sqlaudit_collect')
BEGIN
    CREATE LOGIN [sqlaudit_collect]
        WITH PASSWORD = N'Remplacez-Moi!2026',
             CHECK_POLICY = ON,          -- politique de mot de passe Windows appliquée
             CHECK_EXPIRATION = OFF,     -- compte de service : pas d'expiration
             DEFAULT_DATABASE = master;
END
GO

-- Lecture de l'état du serveur (sessions, configuration)
GRANT VIEW SERVER STATE TO [sqlaudit_collect];
-- Lecture des définitions de tous les objets et principaux (logins, rôles, permissions)
GRANT VIEW ANY DEFINITION TO [sqlaudit_collect];
-- Voir la liste de toutes les bases
GRANT VIEW ANY DATABASE TO [sqlaudit_collect];
-- Se connecter à toutes les bases (pour lire sys.database_principals de chacune)
GRANT CONNECT ANY DATABASE TO [sqlaudit_collect];
GO

-- Vérification : 4 lignes attendues, toutes en GRANT
SELECT p.permission_name, p.state_desc
FROM sys.server_permissions AS p
WHERE p.grantee_principal_id = SUSER_ID(N'sqlaudit_collect');
GO
