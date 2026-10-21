/*
    SQLAudit : annulation des problèmes créés par 02_problemes_de_test.sql
    Exécuter avec un compte sysadmin.
*/
USE master;
GO

-- R16 : désactiver xp_cmdshell
EXEC sp_configure 'show advanced options', 1;
RECONFIGURE;
EXEC sp_configure 'xp_cmdshell', 0;
RECONFIGURE;
EXEC sp_configure 'show advanced options', 0;
RECONFIGURE;
GO

-- R01 et R04 : supprimer les logins de test
IF EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'test_sysadmin')
BEGIN
    ALTER SERVER ROLE [sysadmin] DROP MEMBER [test_sysadmin];
    DROP LOGIN [test_sysadmin];
END
GO
IF EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'test_sans_policy')
    DROP LOGIN [test_sans_policy];
GO

-- R07 : supprimer la base de test (et son utilisateur orphelin)
IF DB_ID(N'SQLAuditTest') IS NOT NULL
BEGIN
    ALTER DATABASE [SQLAuditTest] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE [SQLAuditTest];
END
GO
