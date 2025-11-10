CREATE TABLE [Polaris].[TransactionTypes] (
    [TransactionTypeID] int,
    [TransactionTypeDescription] nvarchar(100),
    [Mnemonic] nvarchar(50),
    [SysAdminuseTransaction] bit DEFAULT (1),
    [IsImplemented] bit DEFAULT (1),
    PRIMARY KEY ([TransactionTypeID])
);