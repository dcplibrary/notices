USE [Results]
GO

/****** Object:  Table [Polaris].[FineNoticesReport]    Script Date: 11/5/2025 8:04:34 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [Polaris].[FineNoticesReport](
	[PatronID] [int] NOT NULL,
	[PatronName] [nvarchar](165) NULL,
	[ReportingOrgID] [int] NOT NULL,
	[ReturnAddrOrgID] [int] NULL,
	[ReportingOrgName] [nvarchar](80) NULL,
	[ReportingOrgAbbreviation] [nvarchar](15) NULL,
	[ReportingOrgPhoneVoice] [nvarchar](32) NULL,
	[TotalAmountOwed] [money] NULL,
	[MinorPatronID] [int] NULL,
	[MinorPatronBarcode] [nvarchar](20) NULL,
	[MinorPatronName] [nvarchar](165) NULL,
	[ItemRecordID] [int] NULL,
	[ItemBarcode] [nvarchar](20) NULL,
	[CallNumber] [nvarchar](370) NULL,
	[Author] [nvarchar](400) NULL,
	[Title] [nvarchar](600) NULL,
	[TxnDate] [datetime] NULL,
	[Amount] [money] NULL,
	[OwedOrganizationID] [int] NULL,
	[OwedOrganizationAbbr] [nvarchar](15) NULL,
	[DeliveryOptionID] [int] NULL,
	[PatronAddressID] [int] NULL,
	[PatronAddress] [nvarchar](400) NULL,
	[ReportingOrgAddress] [nvarchar](400) NULL,
	[PatronCity] [nvarchar](32) NULL,
	[PatronZip] [nvarchar](17) NULL,
	[AdminLanguageID] [int] NULL,
	[TxnID] [int] NULL
) ON [PRIMARY]
GO

