CREATE TABLE [Polaris].[AddressLabels] (
    [AddressLabelID] int IDENTITY,
    [Description] nvarchar(255),
    [Display] bit DEFAULT ((1)),
    [DisplayOrder] int,
    PRIMARY KEY ([AddressLabelID])
);