CREATE TABLE [Polaris].[ShelfLocations] (
    [ShelfLocationID] int,
    [OrganizationID] int,
    [Description] nvarchar(80),
    CONSTRAINT [fk_OrgShelfLoc] FOREIGN KEY ([OrganizationID]) REFERENCES [Polaris].[Organizations]([OrganizationID]),
    PRIMARY KEY ([ShelfLocationID],[OrganizationID])
);