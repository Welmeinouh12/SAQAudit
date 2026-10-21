/*
    SQLAudit : création VOLONTAIRE de problèmes détectables (serveur de TEST uniquement)
    ----------------------------------------------------------------------------------
    ⚠ À n'exécuter QUE sur votre SQL Server local de développement, jamais en production.
    Exécuter avec un compte sysadmin. Pour tout annuler : 03_nettoyage_test.sql

    Constats attendus après un audit :
      R04 : test_sans_policy           (CHECK_POLICY = OFF)
      R01 : test_sysadmin              (membre de sysadmin, hors liste blanche)
      R07 : SQLAuditTest.test_orphelin (utilisateur orphelin)
      R16 : xp_cmdshell                (value_in_use = 1)
*/
USE master;
GO

-- 1. Login SQL sans politique de mot de passe (R04)
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'test_sans_policy')
    CREATE LOGIN [test_sans_policy] WITH PASSWORD = N'Test-SansPolicy-2026!', CHECK_POLICY = OFF;
GO

-- 2. Login ajouté au rôle sysadmin (R01)
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'test_sysadmin')
    CREATE LOGIN [test_sysadmin] WITH PASSWORD = N'Test-Sysadmin-2026!', CHECK_POLICY = ON;
GO
ALTER SERVER ROLE [sysadmin] ADD MEMBER [test_sysadmin];
GO

-- 3. Utilisateur orphelin (R07) : on crée un login + un utilisateur, puis on supprime le login
IF DB_ID(N'SQLAuditTest') IS NULL
    CREATE DATABASE [SQLAuditTest];
GO
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'test_orphelin')
    CREATE LOGIN [test_orphelin] WITH PASSWORD = N'Test-Orphelin-2026!', CHECK_POLICY = ON;
GO
USE [SQLAuditTest];
GO
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'test_orphelin')
    CREATE USER [test_orphelin] FOR LOGIN [test_orphelin];
GO
USE master;
GO
DROP LOGIN [test_orphelin];   -- l'utilisateur de SQLAuditTest devient orphelin
GO

-- 4. Activation de xp_cmdshell (R16)
EXEC sp_configure 'show advanced options', 1;
RECONFIGURE;
EXEC sp_configure 'xp_cmdshell', 1;
RECONFIGURE;
GO

-- Vérification
SELECT 'R04' AS regle, name AS objet FROM sys.sql_logins WHERE name = N'test_sans_policy' AND is_policy_checked = 0
UNION ALL
SELECT 'R01', m.name FROM sys.server_role_members rm
    JOIN sys.server_principals r ON r.principal_id = rm.role_principal_id
    JOIN sys.server_principals m ON m.principal_id = rm.member_principal_id
    WHERE r.name = N'sysadmin' AND m.name = N'test_sysadmin'
UNION ALL
SELECT 'R07', dp.name FROM [SQLAuditTest].sys.database_principals dp
    LEFT JOIN sys.server_principals sp ON sp.sid = dp.sid
    WHERE dp.name = N'test_orphelin' AND sp.sid IS NULL
UNION ALL
SELECT 'R16', name FROM sys.configurations WHERE name = N'xp_cmdshell' AND CAST(value_in_use AS int) = 1;
GO
