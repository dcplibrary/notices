CREATE TABLE [Polaris].[Organizations] (
    [OrganizationID] int IDENTITY,
    [ParentOrganizationID] int,
    [OrganizationCodeID] int,
    [Name] nvarchar(50),
    [Abbreviation] nvarchar(15),
    [SA_ContactPersonID] int,
    [CreatorID] int DEFAULT ((1)),
    [ModifierID] int,
    [CreationDate] datetime,
    [ModificationDate] datetime,
    [DisplayName] nvarchar(50),
    CONSTRAINT [fk_OrgHierarchy] FOREIGN KEY ([ParentOrganizationID]) REFERENCES [Polaris].[Organizations]([OrganizationID]),
    CONSTRAINT [fk_ContactPersons] FOREIGN KEY ([SA_ContactPersonID]) REFERENCES [Polaris].[SA_ContactPersons]([SA_ContactPersonID]),
    CONSTRAINT [fk_Organizations_PolarisUsers_CreatorID] FOREIGN KEY ([CreatorID]) REFERENCES [Polaris].[PolarisUsers]([PolarisUserID]),
    CONSTRAINT [fk_Organizations_PolarisUsers_ModifierID] FOREIGN KEY ([ModifierID]) REFERENCES [Polaris].[PolarisUsers]([PolarisUserID]),
    CONSTRAINT [fk_OrganizationHierarchyType] FOREIGN KEY ([OrganizationCodeID]) REFERENCES [Polaris].[OrganizationCodes]([OrganizationCodeID]),
    PRIMARY KEY ([OrganizationID])
);