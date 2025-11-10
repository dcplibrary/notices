CREATE TABLE [Polaris].[PatronFineNotices] (
    [PatronID] int,
    [ReportingOrgID] int,
    [FineNoticeDate] datetime,
    [Amount] money,
    [DeliveryOptionID] int,
    [FineNotice2ndDate] datetime,
    CONSTRAINT [fk_PatronFineNotices_Organizations] FOREIGN KEY ([ReportingOrgID]) REFERENCES [Polaris].[Organizations]([OrganizationID]),
    CONSTRAINT [fk_PatronFineNotices_Patrons] FOREIGN KEY ([PatronID]) REFERENCES [Polaris].[Patrons]([PatronID]),
    PRIMARY KEY ([PatronID],[ReportingOrgID])
);