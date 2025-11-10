CREATE TABLE [Polaris].[PatronAddresses] (
    [PatronID] int,
    [AddressID] int,
    [AddressTypeID] int,
    [Verified] bit DEFAULT ((0)),
    [VerificationDate] datetime,
    [PolarisUserID] int,
    [AddressLabelID] int DEFAULT ((1)),
    CONSTRAINT [fk_PatAddress] FOREIGN KEY ([AddressID]) REFERENCES [Polaris].[Addresses]([AddressID]),
    CONSTRAINT [fk_PatronAdd] FOREIGN KEY ([PatronID]) REFERENCES [Polaris].[Patrons]([PatronID]),
    CONSTRAINT [fk_AddressLabels] FOREIGN KEY ([AddressLabelID]) REFERENCES [Polaris].[AddressLabels]([AddressLabelID]),
    CONSTRAINT [fk_PatronAddresses_PolarisUsers_PolarisUserID] FOREIGN KEY ([PolarisUserID]) REFERENCES [Polaris].[PolarisUsers]([PolarisUserID]),
    CONSTRAINT [fk_PatAddTypes] FOREIGN KEY ([AddressTypeID]) REFERENCES [Polaris].[AddressTypes]([AddressTypeID])
);