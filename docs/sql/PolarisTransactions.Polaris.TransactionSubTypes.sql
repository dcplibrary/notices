CREATE TABLE [Polaris].[TransactionSubTypes] (
    [TransactionSubTypeID] int,
    [TransactionSubTypeDescription] nvarchar(100),
    [DataType] char(1),
    [Mnemonic] nvarchar(100),
    [IsImplemented] bit DEFAULT ((1)),
    PRIMARY KEY ([TransactionSubTypeID])
);