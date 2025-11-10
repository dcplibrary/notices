CREATE TABLE [Polaris].[PostalCodes] (
    [PostalCodeID] int IDENTITY,
    [PostalCode] nvarchar(12),
    [City] nvarchar(32),
    [State] nvarchar(32),
    [CountryID] int,
    [County] nvarchar(32),
    CONSTRAINT [fk_PostalCodeCountries] FOREIGN KEY ([CountryID]) REFERENCES [Polaris].[Countries]([CountryID]),
    PRIMARY KEY ([PostalCodeID])
);