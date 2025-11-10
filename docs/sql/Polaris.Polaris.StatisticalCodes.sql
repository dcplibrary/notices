CREATE TABLE [Polaris].[StatisticalCodes] (
    [StatisticalCodeID] int,
    [OrganizationID] int,
    [Description] nvarchar(80),
    CONSTRAINT [fk_OrgStatisticalCodes] FOREIGN KEY ([OrganizationID]) REFERENCES [Polaris].[Organizations]([OrganizationID]),
    PRIMARY KEY ([StatisticalCodeID],[OrganizationID])
);