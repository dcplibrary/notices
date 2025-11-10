CREATE TABLE [Polaris].[Addresses] (
    [AddressID] int IDENTITY,
    [PostalCodeID] int,
    [StreetOne] nvarchar(64),
    [StreetTwo] nvarchar(64),
    [ZipPlusFour] nvarchar(4),
    [MunicipalityName] nvarchar(64),
    [StreetThree] nvarchar(64),
    CONSTRAINT [fk_PostalCodeAdd] FOREIGN KEY ([PostalCodeID]) REFERENCES [Polaris].[PostalCodes]([PostalCodeID]),
    PRIMARY KEY ([AddressID])
);