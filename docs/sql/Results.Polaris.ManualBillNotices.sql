USE [Results]
GO

/****** Object:  Table [Polaris].[ManualBillNotices]    Script Date: 11/5/2025 8:10:01 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [Polaris].[ManualBillNotices](
	[TxnID] [int] NULL,
	[ItemRecordID] [int] NULL,
	[PatronID] [int] NULL,
	[ItemBarcode] [nvarchar](20) NULL,
	[BrowseTitle] [nvarchar](255) NULL,
	[BrowseAuthor] [nvarchar](255) NULL,
	[ItemCallNumber] [nvarchar](370) NULL,
	[Abbreviation] [nvarchar](15) NULL,
	[Name] [nvarchar](50) NULL,
	[PhoneNumber] [nvarchar](20) NULL,
	[ReportingOrgID] [int] NULL,
	[DeliveryOptionID] [int] NULL,
	[ReturnAddressOrgID] [int] NULL,
	[NotificationTypeID] [int] NULL,
	[AdminLanguageID] [int] NULL,
	[MaterialTypeID] [int] NULL,
	[FeeReasonCodeID] [int] NULL,
	[AddedMessage] [nvarchar](255) NULL,
	[ChargingLibraryID] [int] NULL,
	[TxnDate] [datetime] NULL,
	[Amount] [money] NULL
) ON [PRIMARY]
GO

