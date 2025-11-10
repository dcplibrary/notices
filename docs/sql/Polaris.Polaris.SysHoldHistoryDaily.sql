CREATE TABLE [Polaris].[SysHoldHistoryDaily] (
    [SysHoldHistoryID] int IDENTITY,
    [SysHoldRequestID] int,
    [SysHoldStatusID] int,
    [StatusTransitionDate] datetime,
    [CreatorID] int,
    [ItemRecordID] int,
    [ActionTakenID] int,
    [OrganizationID] int,
    [WorkstationID] int,
    CONSTRAINT [fk_SysHoldRequestID_SysHoldHistoryDaily] FOREIGN KEY ([SysHoldRequestID]) REFERENCES [Polaris].[SysHoldRequests]([SysHoldRequestID]),
    CONSTRAINT [fk_ActionTakenID_SysHoldHistoryDaily] FOREIGN KEY ([ActionTakenID]) REFERENCES [Polaris].[SysHoldHistoryActions]([ActionTakenID]),
    PRIMARY KEY ([SysHoldHistoryID])
);