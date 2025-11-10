CREATE TABLE [Polaris].[MaterialTypes] (
    [MaterialTypeID] int,
    [Description] nvarchar(80),
    [MinimumAge] int DEFAULT ((0)),
    PRIMARY KEY ([MaterialTypeID])
);