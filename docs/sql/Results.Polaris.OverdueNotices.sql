USE [Results]
GO

/****** Object:  Table [Polaris].[OverdueNotices]    Script Date: 11/5/2025 7:54:03 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [Polaris].[OverdueNotices](
	[ItemRecordID] [int] NOT NULL,
	[PatronID] [int] NOT NULL,
	[ItemBarcode] [nvarchar](20) NULL,
	[DueDate] [datetime] NULL,
	[BrowseTitle] [nvarchar](255) NULL,
	[BrowseAuthor] [nvarchar](255) NULL,
	[ItemCallNumber] [nvarchar](370) NULL,
	[Price] [money] NULL,
	[Abbreviation] [nvarchar](15) NULL,
	[Name] [nvarchar](50) NULL,
	[PhoneNumberOne] [nvarchar](20) NULL,
	[LoaningOrganizationID] [int] NULL,
	[FineCodeID] [int] NULL,
	[LoanUnits] [int] NULL,
	[BillingNotice] [tinyint] NULL,
	[ReplacementCost] [money] NULL,
	[OverdueCharge] [money] NULL,
	[ReportingOrgID] [int] NULL,
	[DeliveryOptionID] [int] NULL,
	[ReturnAddressOrgID] [int] NULL,
	[NotificationTypeID] [int] NULL,
	[IncludeClaimedItems] [bit] NOT NULL,
	[ProcessingCharge] [money] NULL,
	[AdminLanguageID] [int] NULL,
	[OverdueNoticeID] [int] IDENTITY(1,1) NOT NULL,
	[BaseProcessingCharge] [money] NULL,
	[BaseReplacementCost] [money] NULL,
 CONSTRAINT [pk_OverdueNotices] PRIMARY KEY CLUSTERED 
(
	[OverdueNoticeID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO

ALTER TABLE [Polaris].[OverdueNotices] ADD  CONSTRAINT [DF_OverdueNotices_IncludeClaimedItems]  DEFAULT ((0)) FOR [IncludeClaimedItems]
GO

