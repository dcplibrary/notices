CREATE TABLE [Polaris].[ItemStatuses] (
    [ItemStatusID] int IDENTITY,
    [Description] nvarchar(80),
    [Name] nvarchar(25),
    [BannerText] nvarchar(15),
    PRIMARY KEY ([ItemStatusID])
);