CREATE TABLE [Polaris].[SysHoldHistory] (
    [SysHoldRequestID] int,
    [SysHoldStatusID] int,
    [StatusTransitionDate] datetime,
    [CreatorID] int,
    [ItemRecordID] int,
    [ActionTakenID] int,
    [OrganizationID] int,
    [WorkstationID] int,
    [SysHoldHistoryID] int,
    CONSTRAINT [fk_SysHoldHistory_Workstations_WorkstationID] FOREIGN KEY ([WorkstationID]) REFERENCES [Polaris].[Workstations]([WorkstationID]),
    CONSTRAINT [fk_SysHoldStatusID_SysHoldHistory] FOREIGN KEY ([SysHoldStatusID]) REFERENCES [Polaris].[SysHoldStatuses]([SysHoldStatusID]),
    CONSTRAINT [fk_PolarisUsers_SysHoldHistory] FOREIGN KEY ([CreatorID]) REFERENCES [Polaris].[PolarisUsers]([PolarisUserID]),
    CONSTRAINT [fk_SysHoldHistory_Organizations_OrganizationID] FOREIGN KEY ([OrganizationID]) REFERENCES [Polaris].[Organizations]([OrganizationID]),
    CONSTRAINT [fk_ActionTakenID_SysHoldHistory] FOREIGN KEY ([ActionTakenID]) REFERENCES [Polaris].[SysHoldHistoryActions]([ActionTakenID]),
    CONSTRAINT [fk_SysHoldRequestID_SysHoldHistory] FOREIGN KEY ([SysHoldRequestID]) REFERENCES [Polaris].[SysHoldRequests]([SysHoldRequestID])
);