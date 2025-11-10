USE [Results]
GO

/****** Object:  Table [Polaris].[FineNotices]    Script Date: 11/5/2025 7:59:14 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [Polaris].[FineNotices](
	[PatronID] [int] NOT NULL,
	[ItemRelatedCharges] [money] NULL,
	[NonItemRelatedCharges] [money] NULL,
	[Credit] [money] NULL,
	[TotalOutstandingCharges] [money] NULL,
	[PrimaryPatronID] [int] NULL,
	[DeliveryOptionID] [int] NULL,
	[ReportingOrgID] [int] NULL,
	[TxnID] [int] NULL,
	[IsAdditionalTxt] [bit] NULL,
	[NotificationTypeID] [int] NULL
) ON [PRIMARY]
GO

ALTER TABLE [Polaris].[FineNotices] ADD  CONSTRAINT [DF_FineNotices_IsAdditionalTxt]  DEFAULT ((0)) FOR [IsAdditionalTxt]
GO

ALTER TABLE [Polaris].[FineNotices] ADD  CONSTRAINT [DF_FineNotices_NotificationTypeID]  DEFAULT ((8)) FOR [NotificationTypeID]
GO

